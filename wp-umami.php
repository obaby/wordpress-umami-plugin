<?php
/*
Plugin Name: Umami Stats Display
Description: Display Umami analytics stats on your WordPress site.
Version: 1.9
Author: obaby
Author URI: https://www.h4ck.org.cn
Site: https://nai.dog
*/

// Function to retrieve Umami API token
function get_umami_api_token() {
    $umami_api_url = get_option('umami_website_url').'api/auth/login'; //https://c.oba.by/
    
    $username = get_option('umami_username');
    $password = get_option('umami_password');
    
    if (empty($username) || empty($password)) {
        return false;
    }
    
    $response = wp_remote_post($umami_api_url, array(
        'body' => json_encode(array('username' => $username, 'password' => $password)),
        'headers' => array(
            'Content-Type' => 'application/json',
        ),
    ));
    
    if (is_wp_error($response)) {
        return false;
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    if (isset($data['token'])) {
        return $data['token'];
    } else {
        return false;
    }
}

// Function to retrieve Umami stats
function get_umami_stats() {
    $currentTimestamp = round(microtime(true) * 1000);
    $startDate = round((microtime(true) -86400*20)* 1000);
    $umami_api_url = get_option('umami_website_url').'api/websites/' . get_option('umami_website_id').'/pageviews?unit=day&startAt='.$startDate.'&endAt='.$currentTimestamp;
    // echo $umami_api_url;
    $token = get_umami_api_token();
    
    if (empty($token)) {
        return false;
    }
    
    $response = wp_remote_get($umami_api_url, array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ),
    ));
    
    if (is_wp_error($response)) {
        return false;
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    return $data;
}

// Function to add Umami stats dashboard widget
function add_umami_stats_dashboard_widget() {
    wp_add_dashboard_widget(
        'umami_stats_dashboard_widget',
        'Umami Stats',
        'umami_stats_dashboard_widget_content'
    );
}

// Function to display Umami stats dashboard widget content
function umami_stats_dashboard_widget_content() {
    $umami_stats = get_umami_stats();
    
    if (!$umami_stats) {
        echo 'Unable to retrieve Umami stats. Please check your configuration.';
        return;
    }
    
    // Display chart for the last 10 days
    echo '<h4>Last 20 Days Stats</h4>';
    echo '<canvas id="umamiChart" width="400" height="200"></canvas>';
    
    // Enqueue Chart.js library
    wp_enqueue_script('chart-js', 'https://fastly.jsdelivr.net/npm/chart.js');
    
    // 将 JSON 数据解码为关联数组
    // $dataArray = json_decode($jsonData, true);

    // 初始化空数组用于存放键和值
    $keys = array();
    $values = array();

    $datas = $umami_stats['pageviews'];
    // echo $datas;
    // 遍历关联数组
    foreach ($datas as $key => $value) {
        // 将键和值分别放入对应的数组
        $keys[] = $key;
        $values[] = $value;
    }

    // Add script to generate chart
    echo '<script>';
    echo 'document.addEventListener("DOMContentLoaded", function() {';
    echo 'var ctx = document.getElementById("umamiChart").getContext("2d");';
    echo 'var myChart = new Chart(ctx, {';
    echo 'type: "line",';
    echo 'data: {';
    // echo 'labels: ' . json_encode(array_keys($keys)); 
    echo 'datasets: [{';
    echo 'label: "Pageviews",';
    echo 'data: ' . json_encode(array_values($values));
    echo ',backgroundColor: "rgba(75, 192, 192, 0.2)",';
    echo 'borderColor: "rgba(75, 192, 192, 1)",';
    echo 'borderWidth: 1';
    echo '}]';
    echo '},';
    echo 'options: {';
    echo 'scales: {';
    echo 'y: {';
    echo 'beginAtZero: true';
    echo '}';
    echo '}';
    echo '}';
    echo '});';
    echo '});';
    echo '</script>';
    
    // Display today's stats
    // echo '<h4>Today\'s Stats</h4>';
    // echo '<p>Total Pageviews: ' . $umami_stats['metrics']['pageviews']['today'] . '</p>';
    // echo '<p>Unique Visitors: ' . $umami_stats['metrics']['visitors']['today'] . '</p>';
}

// Function to add Umami settings to the admin menu
function umami_stats_add_menu() {
    add_menu_page(
        'Umami Settings',
        'Umami Settings',
        'manage_options',
        'umami_stats_settings',
        'umami_stats_settings_page'
    );
}

// Function to display Umami settings page
function umami_stats_settings_page() {
    ?>
    <div class="wrap">
        <h2>Umami Settings</h2>
        <form method="post" action="options.php">
            <?php
            settings_fields('umami_stats_options');
            do_settings_sections('umami_stats_options');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Function to register Umami settings
function umami_stats_register_settings() {
    register_setting('umami_stats_options', 'umami_username');
    register_setting('umami_stats_options', 'umami_password');
    register_setting('umami_stats_options', 'umami_website_id');
    register_setting('umami_stats_options', 'umami_website_url');
    
    add_settings_section(
        'umami_stats_section',
        'Umami API Settings',
        'umami_stats_section_callback',
        'umami_stats_options'
    );
    
    add_settings_field(
        'umami_username',
        'Username',
        'umami_username_callback',
        'umami_stats_options',
        'umami_stats_section'
    );
    
    add_settings_field(
        'umami_password',
        'Password',
        'umami_password_callback',
        'umami_stats_options',
        'umami_stats_section'
    );

    add_settings_field(
        'umami_website_id',
        'Website ID',
        'umami_website_id_callback',
        'umami_stats_options',
        'umami_stats_section',
    );
    add_settings_field(
        'umami_website_url',
        'Website Url',
        'umami_website_url_callback',
        'umami_stats_options',
        'umami_stats_section',
    );
}

// Callback function for Umami API settings section
function umami_stats_section_callback() {
    echo '<p>Enter your Umami Website Url, API username, password, and website ID below:</p>';
}

// Callback function for Username field
function umami_username_callback() {
    $username = get_option('umami_username');
    echo '<input type="text" name="umami_username" value="' . esc_attr($username) . '" />';
}

// Callback function for Password field
function umami_password_callback() {
    $password = get_option('umami_password');
    echo '<input type="password" name="umami_password" value="' . esc_attr($password) . '" />';
}

// Callback function for Website ID field
function umami_website_id_callback() {
    $website_id = get_option('umami_website_id');
    echo '<input type="text" name="umami_website_id" value="' . esc_attr($website_id) . '" />';
}

function umami_website_url_callback() {
    $website_url = get_option('umami_website_url');
    echo '<input type="text" name="umami_website_url" value="' . esc_attr($website_url) . '" />';
}

// Hook to add Umami stats dashboard widget
add_action('wp_dashboard_setup', 'add_umami_stats_dashboard_widget');

// Hook to add Umami settings to the admin menu
add_action('admin_menu', 'umami_stats_add_menu');

// Hook to register Umami settings
add_action('admin_init', 'umami_stats_register_settings');
?>
