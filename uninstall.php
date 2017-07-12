<?php
/**
 * Uninstaller script
 *
 * @since 1.0.0
 */

//if uninstall not called from WordPress exit
if ( !defined( 'WP_UNINSTALL_PLUGIN' ) ) 
    exit();

delete_option( 'fftf_alerts_options' );