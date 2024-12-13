<?php
define( 'WP_CACHE', false ); // By SiteGround Optimizer

/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'dbgsi1gfgjyngo' );

/** MySQL database username */
define( 'DB_USER', 'uvheimakungyf' );

/** MySQL database password */
define( 'DB_PASSWORD', 'mhduthpws6fs' );

/** MySQL hostname */
define( 'DB_HOST', '127.0.0.1' );

/** Database Charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The Database Collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',          'k`GZ0Ad:&U7a7!BNN1q>GWA3W.A!.w.QU8]$Q,id*zf|H//UVvWa+Ej?DAvLm``1' );
define( 'SECURE_AUTH_KEY',   'Y;%7~X[AP0.g_5dw%Y9OSa[-i*8Ktcm^*4w<jN&[^=%BxF5#Ho&WQtBIPNz.-+0.' );
define( 'LOGGED_IN_KEY',     'FtlDRA 2ERjB)Z+Mz|0T7_90*>p ?H_*qK#9Jy^cYr6tP1rZH3>cELM[<FW<>i?}' );
define( 'NONCE_KEY',         '-#}~7.$8<Zxs.lNW,$ziKwJ5U+|lbwal$T!jZ?:V1$,@4|,[Z99+M.vH>E|^?El3' );
define( 'AUTH_SALT',         '$p[={=A`CJz t)Np}`ICq(},PSM>Q!U_ &lu)(7A]#-z}p:i3E;A#d|TX>lyAMI)' );
define( 'SECURE_AUTH_SALT',  '7ZQ5XhwlexL6{wCqUr9%gm8?uL%f[vp;]kwK~>{kVFaHwY,}wEZs^|X4pRgOmf6e' );
define( 'LOGGED_IN_SALT',    'o1@-oA$Rm k`I4mC#jI=T>hc^ CdU{xmM$&`8It]AL}IZTn9t./s[-,Jl?[6?qI}' );
define( 'NONCE_SALT',        'P?`cg*nOr9iXjxM5gy)fO2?r5L)o2G4E=vT3De*Z^~dZ*$N)f~y!p`OjS jA3g;#' );
define( 'WP_CACHE_KEY_SALT', '2@8VoxW]xVorVYk<V+WuaTal5-p,aUTpNeL}oU1g_glyb3au^qW<~Dg+R =m;gO/' );

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'yhi_';



define( 'WP_AUTO_UPDATE_CORE', false );
define( 'AUTOMATIC_UPDATER_DISABLED', true );
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __FILE__ ) . '/' );
}

/** Sets up WordPress vars and included files. */
@include_once('/var/lib/sec/wp-settings-pre.php'); // Added by SiteGround WordPress management system
require_once ABSPATH . 'wp-settings.php';
@include_once('/var/lib/sec/wp-settings.php'); // Added by SiteGround WordPress management system
