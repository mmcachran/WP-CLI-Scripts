<?php
/**
 * Imports images from post_content into the media library.
 *
 * @package WP CLI Scripts
 */

/**
 * CLI Migration Command for images.
 */
class WP_CLI_Scripts_Migration_Images extends WP_CLI_Scripts_Migration_Base {
	/**
	 * Base URL for images.
	 *
	 * @var  string
	 */
	const PROD_BASE_URL = 'https://example.com/';

	/**
	 * Holds images and URLs that have already been imported.
	 *
	 * @var array
	 */
	protected $images = array();

	/**
	 * Patterns to match external images with.
	 *
	 * @var array
	 */
	protected static $external_image_patterns = array(
		'/(\.\.\/)/',
		'/^(\/images\/)/',
	);

	/**
	 * Migrate images in post content.
	 *
	 * @param  array $args        Command arguments.
	 * @param  array $assoc_args  Command associative arguments.
	 * @return void
	 */
	public function run( $args = array(), $assoc_args = array() ) {
		// Query all posts on the site.
		$query = self::get_query();

		// Bail early if no posts are found.
		if ( ! $query->have_posts() ) {
			self::log( 'No posts were found.', 'error' );
		}

		// Start the progess bar.
		$progress_bar = \WP_CLI\Utils\make_progress_bar(
			sprintf( 'Attempting to import images from %1$d posts.', $query->found_posts ),
			$query->found_posts
		);

		// Keep track of how many posts were updated.
		$posts_updated = 0;

		// Loop through posts to search for images.
		foreach ( (array) $query->posts as $post ) {
			// Determine if post has external images and if so import them and swap the URL in content.
			$external_images_found = $this->import_external_images( $post );

			// Update posts updated count if post had external images.
			if ( $external_images_found ) {
				++$posts_updated;
			}
			// Update the progress bar.
			$progress_bar->tick();
		}

		// Finsh the progress bar.
		$progress_bar->finish();

		// Determine number of images.
		$image_count = count( $this->images );

		self::log( sprintf( '%1$d images imported!', $image_count ) );
		self::log( sprintf( '%1$d posts updated!', $posts_updated ) );
	}

	/**
	 * Import external images and replace URLs.
	 *
	 * @param  WP_Post $post  The post to search for external images in.
	 * @return bool           True if external images were imported, false otherwise.
	 */
	protected function import_external_images( $post ) {
		// Holds properties of the post to search for external images in.
		$properties_to_search = array(
			'post_content',
		);

		// Response for whether or not external images were updated in the post.
		$external_images_found = false;

		// Loop through properties and search for external images.
		foreach ( (array) $properties_to_search as $property ) {
			// Skip if property isn't found.
			if ( empty( $post->{$property} ) ) {
				continue;
			}

			// Skip if property isn't a string.
			if ( ! is_string( $post->{$property} ) ) {
				continue;
			}

			// Find external images and import/swap if necessary.
			$doc = new DOMDocument( '1.0', 'UTF-8' );

			// Avoid errors from improper html.
			libxml_use_internal_errors( true );

			// Load content and find images.
			$doc->loadHTML( utf8_decode( $post->{$property} ) );
			$images = $doc->getElementsByTagName( 'img' );

			// Skip if no tags are found.
			if ( empty( $images ) ) {
				continue;
			}

			// Loop through images and determine if it's external.
			foreach ( $images as $image ) {
				// Get the image URI.
				$src = $image->getAttribute( 'src' );

				// Loop through known external image patterns.
				foreach ( (array) self::$external_image_patterns as $pattern ) {
					// Skip if patten doesn't match.
					if ( ! preg_match( $pattern, $src ) ) {
						continue;
					}

					// Get all matches.
					preg_match_all( $pattern, $src, $matches );

					// Skip if src doesn't contain the pattern.
					if ( empty( $matches ) ) {
						continue;
					}

					// Get the amount of ../ to replace.
					$replace = implode( '', $matches[0] );

					// Workaround so we don't replace images in the url.
					if ( stristr( $replace, 'images' ) ) {
						$full_url = untrailingslashit( self::PROD_BASE_URL ) . $src;
					} else {
						// Get the full url to the image.
						$full_url = str_replace( $replace, self::PROD_BASE_URL, $src );
					}

					// Try to find the attachment ID in cache.
					if ( isset( $this->images[ $full_url ] ) ) {
						$attachment_id = $this->images[ $full_url ];
					} else {
						// Download the image and get the attachment ID.
						$attachment_id = $this->upload_remote_image( $full_url, $post->ID );
					}

					// Skip attachment if no ID.
					if ( is_wp_error( $attachment_id ) || empty( $attachment_id ) ) {
						self::log(
							sprintf( 'Image for %1$d was unsuccessfully uploaded: %2$s', $post->ID, $full_url ),
							'warning'
						);

						continue;
					}

					// Get the attachment URL.
					$attachment_url = wp_get_attachment_url( $attachment_id );

					// Skip if no URL.
					if ( is_wp_error( $attachment_url ) || empty( $attachment_url ) ) {
						self::log(
							sprintf( 'Image URL for %1$d was not found: %2$s', $post->ID, $full_url ),
							'warning'
						);

						continue;
					}

					// cache the attachment ID.
					$this->images[ $src ] = wp_get_attachment_url( $attachment_id );

					// Swap the image src.
					//$image->setAttribute( 'src', $attachment_url );

					// Switch to true since external images were found.
					$external_images_found = true;
				}
			}

			// Save the new post property if external images were found.
			if ( $external_images_found ) {
				$search = array_keys( $this->images );
				$replace = array_values( $this->images );

				// Replace the images.
				$post->{$property} = str_replace( $search, $replace, $post->{$property} );
			}
		}

		// Update the post if we found external images.
		if ( $external_images_found ) {
			wp_update_post( $post );
		}

		return $external_images_found;
	}

	/**
	 * Uploads remote image to WP
	 *
	 * @param  string $url 	    The URL of the image to download.
	 * @param  int    $post_id  The post to attach the image to.
	 * @return int|bool 	    The attachment ID or false on failure.
	 */
	public function upload_remote_image( $url, $post_id ) {
		// Make sure to include the WordPress media uploader API if it's not (front-end).
		if ( ! function_exists( 'download_url' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/image.php' );
			require_once( ABSPATH . 'wp-admin/includes/file.php' );
			require_once( ABSPATH . 'wp-admin/includes/media.php' );
		}

		// Attempt to download the image.
		$tmp = download_url( $url );

		// Bail early if image couldn't be downloaded.
		if ( is_wp_error( $tmp ) ) {
			return new WP_Error( 'error_retrieving_image', 'Error retriving image' );
		}

		$file_array = array();

		// Fix file filename for query strings.
		preg_match( '/[^\?]+\.(jpg|jpe|jpeg|gif|png)/i', $url, $matches );

		// Bail early if not correct format.
		if ( empty( $matches ) ) {
			return false;
		}

		$file_array['name'] = basename( $matches[0] );
		$file_array['tmp_name'] = $tmp;

		// Do the validation and storage stuff.
		$attachment_id = media_handle_sideload( $file_array, $post_id );

		return $attachment_id;
	}
}
