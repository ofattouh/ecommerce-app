<?php

/**
 * The base configurations of the WordPress.
 *
 * This file has the following configurations: MySQL settings, Table Prefix,
 * Secret Keys, WordPress Language, and ABSPATH. You can find more information
 * by visiting {@link http://codex.wordpress.org/Editing_wp-config.php Editing
 * wp-config.php} Codex page. You can get the MySQL settings from your web host.
 *
 * This file is used by the wp-config.php creation script during the
 * installation. You don't have to use the web site, you can just copy this file
 * to "wp-config.php" and fill in the values.
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */

// Only 2 revisions
define('WP_CACHE', true);
define( 'WPCACHEHOME', 'C:\wamp64\www\example.com\wp-content\plugins\wp-super-cache/' );
define( 'WP_POST_REVISIONS', 2 ); 

// save post revisions to the browser in seconds
define( 'AUTOSAVE_INTERVAL', 160 );

define('DB_NAME', 'DB_localhost');

/** MySQL database username */
define('DB_USER', 'root');

/** MySQL database password */
define('DB_PASSWORD', '');

/** MySQL hostname */
define('DB_HOST', 'localhost');

/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8');

/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         'w^ez*nPv={=-hcH~o`}_n%Ew`}VclfN7e[;h1[1@U+LbjX<o)EDGn$#(jKvC4a0Y');
define('SECURE_AUTH_KEY',  'LFnK7.`|BOAt&*vD8W-Elh>b}]J1YgY_~KX#&k3GtDoejK3*W6eEMbKYEi#.O%t{');
define('LOGGED_IN_KEY',    '%-Fb&u*.#3+YEK+Ez%F}lQM; i1`^{Tm*r}Y}]Vuwm.=3B3%9)zH<jH%BHq[55Y6');
define('NONCE_KEY',        '4|VSFBVg8M+t?am]zjKAhP!T+RVLX<!8%M#ANDz8.WM7)|`Kvo.38`^TEW)p@CtG');
define('AUTH_SALT',        'IZ~NyGpp{5uOe~-Ej)@#PCNX9hNON,-4s``1WJ:,$E|Wj6nr.s/hV.pz,fj-d@9O');
define('SECURE_AUTH_SALT', '^|?mnwK!;E<)$[4_Iw%F< ))JCu|{IW5;H`9^W<03zFivu}/H0}c~OsN4x0](WFd');
define('LOGGED_IN_SALT',   'TkFZ=k!t+xROH(LYVd(9LAXP|fdVs.n-+iQy|*C3g/:`xFw-{D.p(Rrztcxz;YND');
define('NONCE_SALT',       'T~dy-F4C+M%VF1p(pLvH*lmnl2zjc&Yul0+ZCl(-w9{;2yfoSvkh&Mrb7r,z4sYa');

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each a unique
 * prefix. Only numbers, letters, and underscores please!
 */
$table_prefix  = 'ckv_';

/**
 * WordPress Localized Language, defaults to English.
 *
 * Change this to localize WordPress. A corresponding MO file for the chosen
 * language must be installed to wp-content/languages. For example, install
 * de_DE.mo to wp-content/languages and set WPLANG to 'de_DE' to enable German
 * language support.
 */
define('WPLANG', '');

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 */
define('WP_DEBUG', false);

/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/* Increase the PHP memory limit allowed for word press - Added by Omar */
define('WP_MEMORY_LIMIT', '512M'); 

/* Disable all types of automatic updates */
define( 'AUTOMATIC_UPDATER_DISABLED', true );


/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');
