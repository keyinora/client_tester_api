# Updated Documentation for `Client_Status_API`

## Overview

This updated documentation reflects the changes made to the `Client_Status_API` class. The class is designed to manage and monitor client websites using WordPress cron jobs, REST API routes, and external API integrations.

---

## Key Changes

### 1. **Cron Job Scheduling Optimization**
   - Cron jobs are now scheduled using the `register_activation_hook` to ensure they are only set up when the plugin is activated, reducing unnecessary checks on every page load.

### 2. **Code Duplication Removed**
   - The logic for iterating through clients and updating their sites has been refactored into smaller reusable methods (`get_all_clients`, `update_client_sites_availability`, and `update_matching_client_site`).

### 3. **Constants for Reusability**
   - Frequently used strings (e.g., post type names and field keys) have been replaced with constants for better maintainability.

---

## Updated Code Breakdown

### **Class Properties**

```php
private $API_BETTER_UPTIME_TOKEN = get_field('better_uptime_api_key', 'options');
private $ADMIN_EMAIL = get_bloginfo('admin_email');
private $SITE_UPDATE_STATS_API = get_field('site_update_url', 'options');
private const OXY_CLIENTS_POST_TYPE = 'oxy_clients';
private const OXY_CLIENTS_DATA_FIELD = 'oxy_clients_data';
```

- `$API_BETTER_UPTIME_TOKEN`: Stores the Better Uptime API token retrieved from ACF options.
- `$ADMIN_EMAIL`: Stores the admin email address for notifications.
- `$SITE_UPDATE_STATS_API`: Stores the external API URL for site update statistics.
- Constants are used for post types and field keys.

---

### **Cron Job Setup**

```php
public function setup_cron_jobs() {
    if (!wp_next_scheduled('update_site_availability_cron')) {
        wp_schedule_event(time(), 'biweekly', 'update_site_availability_cron');
    }
    if (!wp_next_scheduled('update_get_site_update_count_cron')) {
        wp_schedule_event(strtotime('last day of this month 23:59:59'), 'monthly', 'update_get_site_update_count_cron');
    }
}
```

- Ensures cron jobs are scheduled only once during plugin activation.

---

### **Refactored Methods**

#### **Get All Clients**

```php
private function get_all_clients() {
    return get_posts(array(
        'post_type' => self::OXY_CLIENTS_POST_TYPE,
        'posts_per_page' => -1,
    ));
}
```

- Retrieves all posts of type `oxy_clients`.

#### **Update Client Sites Availability**

```php
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
```

- Updates the availability status of all sites for a given client.

#### **Update Matching Client Site**

```php
private function update_matching_client_site($clients, $site_data) {
    $site_url = $site_data['url'];
    $updates = $site_data['response'];

    foreach ($clients as $client) {
        $client_sites = get_field(self::OXY_CLIENTS_DATA_FIELD, $client->ID);
        if ($client_sites && isset($client_sites['sites'])) {
            foreach ($client_sites['sites'] as $index => $site) {
                if ($this->normalize_url($site_url) === $this->normalize_url($site['site_url'])) {
                    $client_sites['sites'][$index]['core_updates'] = $updates['core-updated'];
                    $client_sites['sites'][$index]['plugin_updates'] = $updates['plugins-updated'];
                    $client_sites['sites'][$index]['backups_before_updates'] = $updates['backups'];
                }
            }
            update_field(self::OXY_CLIENTS_DATA_FIELD, $client_sites, $client->ID);
        }
    }
}
```

- Matches a site from the external API data with client sites and updates its details.

---

### **REST API Routes**

```php
public function register_rest_routes() {
    register_rest_route('oxy/v2', '/client/(?P<client_id>\d+)/', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => array($this, 'rest_api_get_client_site_data_by_id'),
    ));
    register_rest_route('oxy/v2', '/clients/', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => array($this, 'rest_api_get_clients_site_data'),
    ));
}
```

- Registers two REST API endpoints:
  - `/oxy/v2/client/<id>`: Fetches site data for a specific client.
  - `/oxy/v2/clients/`: Fetches site data for all clients.

---

### **Cron Job Execution**

#### Update Availability

```php
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
```

#### Update Site Update Count

```php
public function get_site_update_count() {
    try {
        $api_data = json_decode(wp_remote_retrieve_body(wp_remote_get($this->SITE_UPDATE_STATS_API)), true);
        if (empty($api_data) || !is_array($api_data)) {
            throw new Exception('Invalid or empty response from client report API.');
        }

        // Update client sites with fetched data
        $clients = $this->get_all_clients();
        foreach ($api_data as $site_data) {
            $this->update_matching_client_site($clients, $site_data);
        }

        // Notify success
        $this->notify_cron_success('update_get_site_update_count_cron');
    } catch (Exception $e) {
        // Notify failure
        $this->notify_cron_failure('update_get_site_update_count_cron', $e->getMessage());
    }
}
```

---

### **Notifications**

#### Success Notification

```php
private function notify_cron_success($hook) {
    wp_mail(
        $this->ADMIN_EMAIL,
        "Cron Job '{$hook}' Completed Successfully",
        "The cron job '{$hook}' has completed successfully.",
        array('Content-Type: text/html; charset=UTF-8')
    );
}
```

#### Failure Notification

```php
private function notify_cron_failure($hook, $error_message) {
    wp_mail(
        $this->ADMIN_EMAIL,
        "Cron Job '{$hook}' Failed",
        "The cron job '{$hook}' failed with error: {$error_message}",
        array('Content-Type: text/html; charset=UTF-8')
    );
}
```

---

## Summary of Improvements

1. Cron job setup is optimized to avoid redundant scheduling.
2. Code duplication is reduced by introducing reusable methods.
3. Constants improve readability and maintainability.
4. REST API routes remain unchanged but are well-documented.

This updated documentation reflects the cleaner and more efficient implementation of the `Client_Status_API` class.

Citations:
[1] https://ppl-ai-file-upload.s3.amazonaws.com/web/direct-files/41907225/7eaa1be5-858c-4d34-8324-d2d900287180/paste.txt
[2] https://ppl-ai-file-upload.s3.amazonaws.com/web/direct-files/41907225/2345da79-7345-4f43-b7af-88ac460788c6/paste-2.txt

## Here's a Big-O analysis of the `Client_Status_API` class:

### Overall Complexity

The overall time complexity of this class is O(n * m), where n is the number of clients and m is the average number of sites per client. This is primarily due to the nested loops in the `update_availability_for_sites()` and `get_site_update_count()` methods.

### Method-by-Method Analysis

1. `normalize_url($url)`: O(1)
   - Uses a fixed number of string operations, regardless of input size.

2. `update_availability_for_sites()`: O(n * m)
   - Outer loop iterates over all clients: O(n)
   - Inner loop iterates over each client's sites: O(m)
   - The `get_availability()` call is O(1) as it's a single API request

3. `get_site_update_count()`: O(n * m * k)
   - Outer loop iterates over all sites from the API: O(k)
   - Middle loop iterates over all clients: O(n)
   - Inner loop iterates over each client's sites: O(m)
   - This could potentially be optimized by using more efficient data structures

4. `get_availability($monitor_id)`: O(1)
   - Makes a single API call, which is considered constant time

5. `rest_api_get_client_site_data_by_id($r)`: O(m)
   - Iterates over all sites of a single client

6. `rest_api_get_clients_site_data($r)`: O(n * m)
   - Outer loop iterates over all clients: O(n)
   - Inner loop iterates over each client's sites: O(m)

### Space Complexity

The space complexity is O(n * m) as well, primarily due to storing data for all clients and their sites in memory during operations like `get_site_update_count()`.

### Potential Optimizations

1. Use caching mechanisms to store API responses and reduce API calls.
2. Implement pagination in the REST API endpoints to handle large datasets more efficiently.
3. Use more efficient data structures (e.g., hash tables) for faster lookups when matching sites in `get_site_update_count()`.
4. Consider batch processing for updating site data to reduce database write operations.

These optimizations could significantly improve performance, especially when dealing with a large number of clients and sites.

Citations:
[1] https://ppl-ai-file-upload.s3.amazonaws.com/web/direct-files/41907225/7eaa1be5-858c-4d34-8324-d2d900287180/paste.txt
[2] https://ppl-ai-file-upload.s3.amazonaws.com/web/direct-files/41907225/2345da79-7345-4f43-b7af-88ac460788c6/paste-2.txt
[3] https://ppl-ai-file-upload.s3.amazonaws.com/web/direct-files/41907225/7eaa1be5-858c-4d34-8324-d2d900287180/paste.txt