<?php
/**
 * Image migration.
 *
 * @package WP CLI Scripts
 */

/**
 * CLI Migration Command for categories.
 */
class WP_CLI_Scripts_Migration_Categories extends WP_CLI_Scripts_Migration_Base {
	/**
	 * Holds categories we need to make sure are created and triggers to match on possible posts.
	 *
	 * @var array
	 */
	protected $categories = array(
		'NFL' => array(
			'taxonomy' => 'category',
			'old_category' => 'football',
			'parent' => 840,
			'patterns' => array(
				'/\b(nfl)\b/i'
			),
		),
		'NBA' => array(
			'taxonomy' => 'category',
			'old_category' => 'basketball',
			'parent' => 840,
			'patterns' => array(
				'/\b(nba)\b/i'
			),
		),
	);

	/**
	 * Migrate images in post content.
	 *
	 * @param  array $args        Command arguments.
	 * @param  array $assoc_args  Command associative arguments.
	 * @return void
	 */
	public function run( $args = array(), $assoc_args = array() ) {
		// make sure the categories exist.
		$this->check_for_categories();

		// query all posts on the site.
		$query = self::get_query();

		// bail early if no posts are found.
		if ( ! $query->have_posts() ) {
			self::log( 'No posts were found.', 'error' );
		}

		// start the progess bar.
		$progress_bar = \WP_CLI\Utils\make_progress_bar(
			sprintf( 'Attempting to re-categorize %1$d posts.', $query->found_posts ),
			$query->found_posts
		);

		// keep track of how many posts were updated.
		$posts_updated = 0;

		// loop through posts to look for categories.
		foreach ( (array) $query->posts as $post ) {
			// update the progress bar.
			$progress_bar->tick();

			// loop through categories and check to see if post matches.
			foreach ( (array) $this->categories as $category => $config ) {
				// skip if no patterns.
				if ( ! isset( $config['patterns'] ) ) {
					continue;
				}

				// skip if we don't have a term id.
				if ( empty( $config['term_id'] ) ) {
					self::log( sprintf( '%1$s doesn\'t have a term id!', $category ), 'error' );
				}

				// loop through patterns to check for matches.
				foreach ( (array) $config['patterns'] as $pattern ) {
					// skip if title doesn't match.
					if ( ! preg_match( $pattern, $post->post_title ) ) {
						continue;
					}

					// we know this matches so reset the post's terms.
					$results = wp_set_object_terms( $post->ID, $config['term_id'], $config['taxonomy'] );

					// output error if available.
					if ( is_wp_error( $results ) ) {
						self::log(
							sprintf( '%1$s could not be set on: %2$d', $category, $post->ID ),
							'warning'
						);
					}

					// increment updated count.
					++$posts_updated;
				}
			}
		}

		// finsh the progress bar.
		$progress_bar->finish();

		// output success message.
		self::log( sprintf( '%1$d posts re-categorized!', $posts_updated ) );
	}

	/**
	 * Checks for categories we're adding posts to.
	 *
	 * @return void
	 */
	public function check_for_categories() {
		// loop through categories and check to see if post matches.
		foreach ( (array) $this->categories as $category => $config ) {
			// skip if the term exists.
			if ( category_exists( $category, $config['parent'] ) ) {
				self::log( sprintf( 'Category exists: %1$s', $category ) );

				// get the category object.
				$cat = get_term_by( 'name', $category, $config['taxonomy'] );

				// add term id to the category config.
				$this->categories[ $category ]['term_id'] = $cat->term_id;

				continue;
			}

			// create the category.
			$category_id = wp_create_category( $category, $config['parent'] );

			// bail early if we don't have a category.
			if ( empty( $category_id ) || is_wp_error( $category_id ) ) {
				self::log(
					sprintf( 'Category could not be created: %1$s', $category ),
					'error'
				);

				continue;
			}

			// add term id to the category config.
			$this->categories[ $category ]['term_id'] = $category_id;

			self::log( sprintf( 'Category created: %1$s', $category ) );
		}
	}
}
