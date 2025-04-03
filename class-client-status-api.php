<?php 
class Client_Status_API {
	private $API_BETTER_UPTIME_TOKEN;
	private $ADMIN_EMAIL;
	private $SITE_UPDATE_STATS_API;
	private const OXY_CLIENTS_POST_TYPE = 'oxy_clients';
	private const OXY_CLIENTS_DATA_FIELD = 'oxy_clients_data';

	public function __construct() {
		$this->API_BETTER_UPTIME_TOKEN = get_field('better_uptime_api_key', 'options');
		$this->ADMIN_EMAIL = get_bloginfo('admin_email');
		$this->SITE_UPDATE_STATS_API = get_field('site_update_url', 'options');

		if (!function_exists('get_field')){
			return 'ACF Pro not activated or not installed';
			die(1);
		}
		$this->init_hooks();
	}

	private function init_hooks() {
		add_filter('cron_schedules', array($this, 'add_custom_cron_intervals'));
		add_action('wp', array($this, 'setup_cron_jobs'));
		#register_activation_hook(__FILE__, array($this, 'setup_cron_jobs'));
		add_action('update_site_availability_cron', array($this, 'update_availability_for_sites'));
		add_action('update_get_site_update_count_cron', array($this, 'get_site_update_count'));
		add_action('rest_api_init', array($this, 'register_rest_routes'));
	}
	
	private function get_next_start_of_month() {
        $current_time = current_time('timestamp');
        return strtotime('first day of next month midnight', $current_time);
    }
	
    public function add_custom_cron_intervals($schedules) {
        $schedules['biweekly'] = array(
            'interval' => 14 * DAY_IN_SECONDS,
            'display' => __('Every Two Weeks')
        );
        return $schedules;
    }
	
	public function setup_cron_jobs() {
		if (!wp_next_scheduled('update_site_availability_cron')) {
			wp_schedule_event(time(), 'biweekly', 'update_site_availability_cron');
		}
		if (!wp_next_scheduled('update_get_site_update_count_cron')) {
			wp_schedule_event($this->get_next_start_of_month(), 'monthly', 'update_get_site_update_count_cron');
		}
	}

	public function register_rest_routes() {
		register_rest_route('oxy/v2', '/client/(?P<client_id>\d+)/', array(
			'methods' => WP_REST_Server::READABLE,
			'callback' => array($this, 'rest_api_get_client_site_data_by_id'),
		));
		register_rest_route('oxy/v2', '/clients/', array(
			'methods' => WP_REST_Server::READABLE,
			'callback' => array($this, 'rest_api_get_clients_site_data'),
		));
		
		// manual run
		register_rest_route('oxy/v2', '/get_uptime_status/', array(
			'methods' => WP_REST_Server::READABLE,
			'callback' => array($this, 'update_availability_for_sites'),
		));
		
		register_rest_route('oxy/v2', '/get_updates/', array(
			'methods' => WP_REST_Server::READABLE,
			'callback' => array($this, 'get_site_update_count'),
		));
	}

	private function normalize_url($url) {
		$url = preg_replace('#^https?://#', '', $url);
		$url = preg_replace('/^www\./', '', $url);
		$url = rtrim($url, '/');
		$url = preg_replace('#/(wp|home|index|main)/?$#i', '', $url);
		$url = preg_replace('#/.*$#', '', $url);
		return $url;
	}

	public function update_availability_for_sites() {
		try {
			$clients = $this->get_all_clients();
			foreach ($clients as $client) {
				$this->update_client_sites_availability($client);
			}
			$this->notify_cron_success('update_site_availability_cron');
		} catch (Exception $e) {
			$this->notify_cron_failure('update_site_availability_cron', $e->getMessage());
		}
	}

	private function get_all_clients() {
		return get_posts(array(
			'post_type' => self::OXY_CLIENTS_POST_TYPE,
			'posts_per_page' => -1,
		));
	}

	private function update_client_sites_availability($client) {
		$client_sites = get_field(self::OXY_CLIENTS_DATA_FIELD, $client->ID);
		if ($client_sites && isset($client_sites['sites'])) {
			foreach ($client_sites['sites'] as $index => $site) {
				if (isset($site['uptime_monitor_code']) && !empty($site['uptime_monitor_code'])) {
					$availability = $this->get_availability($site['uptime_monitor_code']);
					$client_sites['sites'][$index]['uptime_status'] = $availability;
				}
			}
			update_field(self::OXY_CLIENTS_DATA_FIELD, $client_sites, $client->ID);
		}
	}

	public function get_site_update_count() {
		try {
			$api_data = $this->fetch_client_report_api_data();
			$this->update_client_sites_data($api_data);
			$this->notify_cron_success('update_get_site_update_count_cron');
		} catch (Exception $e) {
			$this->notify_cron_failure('update_get_site_update_count_cron', $e->getMessage());
		}
	}

	private function fetch_client_report_api_data() {
		$response = wp_remote_get($this->SITE_UPDATE_STATS_API);
		if (is_wp_error($response)) {
			throw new Exception('Error fetching client report API: ' . $response->get_error_message());
		}
		$data = json_decode(wp_remote_retrieve_body($response), true);
		if (empty($data) || !is_array($data)) {
			throw new Exception('Invalid or empty response from client report API.');
		}
		return $data;
	}

	private function update_client_sites_data($api_data) {
		$clients = $this->get_all_clients();
		foreach ($api_data as $site_data) {
			$this->update_matching_client_site($clients, $site_data);
		}
	}

	private function update_matching_client_site($clients, $site_data) {
		$site_url = $site_data['url'];
		$updates = $site_data['response'];
		foreach ($clients as $client) {
			$client_sites = get_field(self::OXY_CLIENTS_DATA_FIELD, $client->ID);
			if ($client_sites && isset($client_sites['sites']) && isset($updates)) {
				foreach ($client_sites['sites'] as $index => $site) {
					$temp_site_url = $site['site_url'];
					if ($site['does_this_host_do_staging_backups']){
						$temp_site_url = $site['alt_site_url'];
					}

					if ($this->normalize_url($site_url) === $this->normalize_url($temp_site_url)) {
						$client_sites['sites'][$index]['core_updates'] = $updates['core-updated'];
						$client_sites['sites'][$index]['plugin_updates'] = $updates['plugins-updated'];
						$client_sites['sites'][$index]['backups_before_updates'] = $updates['backups'];
					}
				}
				update_field(self::OXY_CLIENTS_DATA_FIELD, $client_sites, $client->ID);
			}
		}
	}

	private function notify_cron_success($hook) {
		$to = $this->ADMIN_EMAIL;
		$to = 'ddraven1989@gmail.com';
		$subject = "Cron Job '{$hook}' Completed Successfully";
		$message = "The cron job '{$hook}' has completed successfully.";
		$headers = array('Content-Type: text/html; charset=UTF-8');
		wp_mail($to, $subject, $message, $headers);
	}

	private function notify_cron_failure($hook, $error_message) {
		$to = $this->ADMIN_EMAIL;
		$to = 'ddraven1989@gmail.com';
		$subject = "Cron Job '{$hook}' Failed";
		$message = "The cron job '{$hook}' failed with error: {$error_message}";
		$headers = array('Content-Type: text/html; charset=UTF-8');
		wp_mail($to, $subject, $message, $headers);
	}

	private function get_availability($monitor_id) {
		$end_date = date('Y-m-d');
		$start_date = date('Y-m-d', strtotime('-30 days'));
		$url = "https://uptime.betterstack.com/api/v2/monitors/{$monitor_id}/sla";
		$headers = [
			"Authorization: Bearer {$this->API_BETTER_UPTIME_TOKEN}"
		];
		$params = [
			"from" => $start_date,
			"to" => $end_date
		];
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($params));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		$response = curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		if ($http_code == 200) {
			$data = json_decode($response, true);
			$attributes = $data["data"]["attributes"];
			$availability = isset($attributes["availability"]) ? $attributes["availability"] : "100.00";
			return bcadd($availability, '0', 2);
		} else {
			return "100.00";
		}
	}

	public function rest_api_get_client_site_data_by_id($r) {
		$client_id = ($r['client_id']) ? $r['client_id'] : false;
		$client_sites = get_field(self::OXY_CLIENTS_DATA_FIELD, $client_id);
		$sites = [];
		if ($client_sites && isset($client_sites['sites'])) {
			foreach ($client_sites['sites'] as $site) {
				$sites[] = [
					'site_name' => $site['site_name'],
					'site_url' => $site['site_url'],
					'uptime_status' => $site['uptime_status'],
					'core_updates' => $site['core_updates'],
					'plugin_updates' => $site['plugin_updates'],
					'backups_before_updates' => $site['backups_before_updates']
				];
			}
		}
		return $sites;
	}

	public function rest_api_get_clients_site_data($r) {
		$args = [
			'post_type' => self::OXY_CLIENTS_POST_TYPE,
			'posts_per_page' => -1,
			'post_status' => 'publish'
		];
		$q = new WP_Query($args);
		$sites = [];
		if ($q->have_posts()) {
			while ($q->have_posts()) {
				$q->the_post();
				$client_sites = get_field(self::OXY_CLIENTS_DATA_FIELD);
				if ($client_sites && isset($client_sites['sites'])) {
					foreach ($client_sites['sites'] as $site) {
						if (!$site['include_in_client_reporting']) continue;
						$sites[] = [
							'site_name' => $site['site_name'],
							'site_url' => $site['site_url'],
							'uptime_status' => $site['uptime_status'],
							'core_updates' => $site['core_updates'],
							'plugin_updates' => $site['plugin_updates'],
							'backups_before_updates' => $site['backups_before_updates']
						];
					}
				}
			}
		}
		wp_reset_postdata();
		return $sites;
	}
}