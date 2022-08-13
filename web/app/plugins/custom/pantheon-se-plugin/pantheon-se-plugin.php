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
    private string $image_url = 'https://api.pexels.com/v1/search';
    private string $image_api_key = '563492ad6f917000010000012cfd6eeb3c6043c5958d92cfff1a1681';
    private string $text_url = 'https://transformer.huggingface.co/autocomplete/gpt';

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
     * default: food
     * ---
     *
     * * [--num_posts=<num_posts>]
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
    public function generate($args, $assoc_args)
    {

        // Get Post Details.
        $num_posts = (int)$assoc_args['num_posts'];
        $query = (string)$assoc_args['query'];

        if ($num_posts > 50) {
            WP_CLI::error('You cannot create more than 50 posts.');
        }

        $progress = make_progress_bar('Generating Posts', $num_posts);
        $post_data = $this->get_images($query, $num_posts);

        for ($i = 0; $i < $num_posts; $i++) {

            $image = $post_data[$i];
            // Code used to generate a post.
            $my_post = [
                'post_title' => sanitize_title($image['alt']),
                'post_status' => 'publish',
                'post_author' => $this->create_user($image),
                'post_type' => 'post',
                'post_content' => sanitize_textarea_field($this->get_text($image)),
                'tags_input' => ['generated', $query],
            ];

            // Insert the post into the database.
            $post_id = wp_insert_post($my_post);
            $this->attach_image($post_id, $image);
            WP_CLI::success("Generated post: ${image['alt']}");

            $progress->tick();
        }

        $progress->finish();
        WP_CLI::success($num_posts . ' posts generated!'); // Prepends Success to message
    }

    /**
     * Generate random images.
     *
     * @param string $query
     * @param int $num
     * @return mixed|void
     */
    private function get_images(string $query = 'food', int $num = 20)
    {
        $query = sanitize_text_field($query);
        $url = $this->image_url . http_build_query(['query' => $query, 'per_page' => $num]);
        $request = wp_safe_remote_get($url, [
            'headers' => [
                "Authorization" => $this->image_api_key
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
        $user = get_user_by($ID);
        if (!$user) {
            // Prepare userdata.
            $user_login = wp_slash(sanitize_title($username));
            $user_email = wp_slash($user_login . '@example.com');
            $user_pass = wp_generate_password();
            $display_name = $username;
            $user_url = $url;

            $userdata = compact('user_login', 'user_email', 'user_pass', 'user_url', 'display_name', 'ID');
            $wp_user = wp_insert_user($userdata);
            if (!is_wp_error($wp_user)) {
                return $wp_user;
            } else {
                return 1;
            }
        } else {
            return $user->ID;
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

        // Prepare upload image to WordPress Media Library
        $upload = wp_upload_bits($image_name, null, file_get_contents($image_url));

        // check and return file type
        $image_file = $upload['file'];
        $wpFileType = wp_check_filetype($image_file);

        // Attachment attributes for file
        $attachment = array(
            'post_mime_type' => $wpFileType['type'],  // file type
            'post_title' => sanitize_file_name($image_file),  // sanitize and use image name as file name
            'post_content' => '',  // could use the image description here as the content
            'post_status' => 'inherit'
        );

        // Create attachment
        $attachment_id = wp_insert_attachment($attachment, $image_file, $post_id);
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $image_file);
        wp_update_attachment_metadata($attachment_id, $attachment_data);
        if (set_post_thumbnail($post_id, $attachment_id)) {
            WP_CLI::success("Attachment $image_name added to #$post_id");
        } else {
            WP_CLI::error("Error adding attachment to #$post_id");
        }

    }

    protected function get_text($image)
    {
        $text = $image['alt'];
        $endpoint = $this->text_url;

        $body = [
            "context" => $text,
            "model_size" => "gpt",
            "top_p" => 5,
            "temperature" => 5,
            "max_time" => 2
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
        if (!empty($data['sentences'])) {
            $parts = [];
            foreach ($data['sentences'] as $sentence) {
                $parts[] = $text . $sentence['value'];
            }
            return join(" ", $parts);
        } {
            WP_CLI::error("Could not fetch sentences.");
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
