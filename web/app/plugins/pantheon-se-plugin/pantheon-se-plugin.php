<?php

use function WP_CLI\Utils\make_progress_bar;

/**
 * Plugin Name:     Pantheon SE Demo Plugin
 * Plugin URI:      https://github.com/pantheon-se/pantheon-se-plugin
 * Description:     Simple plugin for setting up demo content.
 * Author:          Kyle Taylor
 * Author URI:      https://github.com/kyletaylored
 * Text Domain:     pantheon_se_plugin
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * @package         Pantheon_se
 */
class PANTHEON_SE_PLUGIN_CLI
{
    private string $image_api = 'https://api.pexels.com/v1';
    private string $image_api_key = '563492ad6f917000010000012cfd6eeb3c6043c5958d92cfff1a1681';
    private string $text_api = 'https://transformer.huggingface.co/autocomplete/gpt';

    /**
     * Prints a greeting.
     *
     * ## OPTIONS
     *
     * <name>
     * : The name of the person to greet.
     *
     * [--type=<type>]
     * : Whether or not to greet the person with success or error.
     * ---
     * default: success
     * options:
     *   - success
     *   - error
     * ---
     *
     * ## EXAMPLES
     *
     *     wp pantheon hello Newman
     *
     * @when after_wp_load
     */
    public function hello($args, $assoc_args)
    {
        list($name) = $args;

        // Print the message with type
        $type = $assoc_args['type'];
        WP_CLI::$type("Hello, $name!");
    }

    /**
     * Generate posts with meta values.
     *
     * ## OPTIONS
     *
     *
     * [--query=<query>]
     * : A category to generate content from.
     * ---
     * default: restaurant
     * ---
     *
     * [--num_posts=<num_posts>]
     * : The number of posts to generate (max 50).
     * ---
     * default: 20
     * ---
     *
     * ## EXAMPLES
     *
     *     wp pantheon generate --query="corporate sales"
     *
     * @when after_wp_load
     *
     * @param Array $args Arguments in array format.
     * @param Array $assoc_args Key value arguments stored in associated array format.
     */
    public function generate(array $args, array $assoc_args)
    {

        // Get Post Details.
        $num_posts = (int)$assoc_args['num_posts'];
        $query = (!empty($assoc_args['query'])) ? (string)$assoc_args['query'] : null;

        if ($num_posts > 50) {
            WP_CLI::error('You cannot create more than 50 posts.');
        }

        $progress = make_progress_bar('Generating Posts', $num_posts);
        $post_data = $this->get_images($query, $num_posts);
        $post_count = count($post_data);
        $posts = [];

        foreach ($post_data as $image) {
            // If no text, skip.
            if (empty($image['alt'])) {
                $post_count--;
                continue;
            }

            // Code used to generate a post.
            $my_post = [
                'post_title' => $image['alt'],
                'post_status' => 'publish',
                'post_author' => $this->create_user($image),
                'post_type' => 'post',
                'post_content' => sanitize_textarea_field($this->get_text($image)),
                'tags_input' => ['generated', $query],
            ];

            // Insert the post into the database.
            $post_id = wp_insert_post($my_post);
            $this->attach_image($post_id, $image);

            $posts[] = array_merge(['id' => $post_id], $my_post);

            // Debug
            WP_CLI::debug("Generated post: ${image['alt']}", __CLASS__ . "->" . __FUNCTION__);

            $progress->tick();
        }

        $progress->finish();

        WP_CLI::success($post_count . ' posts generated!'); // Prepends Success to message
        WP_CLI\Utils\format_items('table', $posts, ['id', 'post_title']);
    }

    /**
     * Generate random images.
     *
     * @param string|null $query
     * @param int $num
     * @return mixed|void
     */
    private function get_images(string $query = null, int $num = 20)
    {
        if (empty($query)) {
            $url = $this->image_api . '/curated?' . http_build_query(['per_page' => $num]);
        } else {
            $query = sanitize_text_field($query);
            $url = $this->image_api . '/search?' . http_build_query(['query' => $query, 'per_page' => $num]);
        }

        WP_CLI::debug("image url: " . $url, __CLASS__ . "->" . __FUNCTION__);
        $request = wp_remote_get($url, [
            'headers' => [
                "Authorization" => $this->image_api_key,
                "Content-Type" => "application/json"
            ]
        ]);

        if (is_wp_error($request)) {
            WP_CLI::error("Could not complete request: $url");
        }

        $body = wp_remote_retrieve_body($request);
        $data = json_decode($body, true);

        if (!empty($data['photos'])) {
            return $data['photos'];
        } else {
            WP_CLI::error("No photos available for \"$query\", choose a new query.");
        }
    }

    /**
     * Create use from image data.
     * @param $image
     * @return int
     */
    protected function create_user($image): int
    {
        $username = $image['photographer'];
        $url = $image['photographer_url'];
        $ID = $image['photographer_id'];
        WP_CLI::debug((string)$ID, __CLASS__ . "->" . __FUNCTION__);

        $user_login = wp_slash(sanitize_title($username));
        $user = get_user_by('login', $user_login);

        if (!$user) {
            // Prepare userdata.
            $user_email = wp_slash($user_login . '@example.com');
            $user_pass = wp_generate_password();
            $display_name = $username;
            $user_url = $url;

            $userdata = compact('user_login', 'user_email', 'user_pass', 'user_url', 'display_name');
            WP_CLI::debug(json_encode($userdata, JSON_PRETTY_PRINT), __CLASS__ . "->" . __FUNCTION__);

            $wp_user = wp_insert_user($userdata);

            if (!is_wp_error($wp_user)) {
                WP_CLI::debug("new user: " . $wp_user, __CLASS__ . "->" . __FUNCTION__);
                return $wp_user;
            } else {
                WP_CLI::debug(json_encode($wp_user, JSON_PRETTY_PRINT), __CLASS__ . "->" . __FUNCTION__);
                return 1;
            }
        } else {
            return $user->ID;
        }
    }

    /**
     * @param $image
     * @return mixed|string
     */
    protected function get_text($image)
    {
        $text = $image['alt'];
        WP_CLI::debug($text, __CLASS__ . "->" . __FUNCTION__);

        $endpoint = $this->text_api;

        $body = [
            "context" => $text,
            "model_size" => "gpt",
            "top_p" => 3,
            "temperature" => 3,
            "max_time" => 1
        ];

        $body = wp_json_encode($body);

        $options = [
            'body' => $body,
            'headers' => [
                'Content-Type' => 'application/json',
                "accept" => "*/*"
            ],
        ];

        $request = wp_remote_post($endpoint, $options);
        if (is_wp_error($request)) {
            WP_CLI::error("Could not complete request: $endpoint");
        }

        $body = wp_remote_retrieve_body($request);
        $data = json_decode($body, true);
        if (!empty($data['sentences'] && is_array($data['sentences']))) {
            WP_CLI::debug(json_encode($data['sentences'], JSON_PRETTY_PRINT), __CLASS__ . "->" . __FUNCTION__);

            $parts = [];
            foreach ($data['sentences'] as $sentence) {
                $parts[] = $text . $sentence['value'];
            }
            return join(" ", $parts);
        }
        {
            return $text;
        }
    }

    /**
     * @param $post_id
     * @param $image
     * @return void
     */
    protected function attach_image($post_id, $image)
    {
        $image_name = $image['alt'];
        $image_url = $image['src']['large'];
        WP_CLI::debug($image_url, __CLASS__ . "->" . __FUNCTION__);

        // Download image
        $temp_file = download_url($image_url);
        WP_CLI::debug($temp_file, __CLASS__ . "->" . __FUNCTION__);

        if (is_wp_error($temp_file)) {
            return false;
        }

        // Move the temp file into the uploads directory.
        $image_url = parse_url($image_url);
        $file = [
            'name' => basename($image_url['path']),
            'type' => mime_content_type($temp_file),
            'tmp_name' => $temp_file,
            'size' => filesize($temp_file),
        ];

        $sideload = wp_handle_sideload($file, ['test_form' => false]);

        // Check error
        if (!empty($sideload['error'])) {
            WP_CLI::error($sideload['error']);
        }

        // Add image into media library
        $attachment = [
            'guid' => $sideload['url'],
            'post_mime_type' => $sideload['type'],
            'post_title' => basename($sideload['file']),
            'post_content' => $image['alt'],
            'post_status' => 'inherit',
        ];

        // Create attachment
        $attachment_id = wp_insert_attachment($attachment, $sideload['file'], $post_id);
        if (is_wp_error($attachment_id) || !$attachment_id) {
            return false;
        }

        $attachment_data = wp_generate_attachment_metadata($attachment_id, $sideload['file']);
        wp_update_attachment_metadata($attachment_id, $attachment_data);
        if (set_post_thumbnail($post_id, $attachment_id)) {
            WP_CLI::debug("Attachment $image_name added to #$post_id", __CLASS__ . "->" . __FUNCTION__);
        } else {
            WP_CLI::error("Error adding attachment to #$post_id");
        }
    }
}

/**
 * Registers WP CLI commands
 */
function pantheon_se_plugin_cli_register_commands()
{
    WP_CLI::add_command('pantheon', 'PANTHEON_SE_PLUGIN_CLI');
}

add_action('cli_init', 'pantheon_se_plugin_cli_register_commands');
