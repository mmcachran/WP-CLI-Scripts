<?php
if ( defined( 'WP_CLI' ) && WP_CLI ):	
	class Post_Cleaner extends WP_CLI_Command {
		public function __construct() {
			// add the filters
			add_filter( 'clean_post_content', array( 'Cleaner', 'strip_styles' ), 10, 1 );
			add_filter( 'clean_post_content', array( 'Cleaner', 'convert_smart_quotes' ), 20, 1 );
			add_filter( 'clean_post_content', array( 'Cleaner', 'remove_empty_tags' ), 30, 1 );
		}
			
		/**
		 * @synopsis [<wp_blog_id>]
		 */
		public function clean_posts( $args, $assoc_args ) {
			$site_id = isset( $args[0] ) ? $args[0] : null;
			$total_cleaned_posts = 0;
			$skipped_posts = array();
			
			if( is_null( $site_id ) && is_multisite() ) {
				WP_CLI::error( "You must supply a site id!" );
				exit;
			}
	
			if( is_multisite() )
				switch_to_blog( $site_id );
	
			$query = new WP_Query( array(
				'post_type' => array( 'post' ),
				'posts_per_page' => -1,
			) );
				
			if( $query->found_posts > 0 ) {
				WP_CLI::line( "" );
				
				$progress_bar = \WP_CLI\Utils\make_progress_bar( "Cleaning $query->found_posts", $query->found_posts );
				
				foreach( $query->posts as $post ) {
					$post_args = array();
					$post_args['ID'] = $post->ID;
						
					// strip styles
					$post_args['post_content'] = apply_filters( 'clean_post_content', '', $post->post_content );
						
					if ( wp_update_post( $post_args ) ) {
						$progress_bar->tick();
						++$total_cleaned_posts;
					} else {
						$skipped_posts[] = $post->post_title;
					}			
				}
				
				$progress_bar->finish();	
				$ratio_cleaned = $total_cleaned_posts . '/' . $query->found_posts;
				
				WP_CLI::line( "" );
				WP_CLI::success( "$ratio_cleaned Posts Cleaned!" );
				WP_CLI::line( "" );
				
				if( ! empty( $skipped_posts ) ) {
					WP_CLI::line( "Skipped Posts: " . print_r( $skipped_posts, true ) );
				}
			}	
		}	
			
		public static function strip_styles( $content ) {
			// strip inline styles
			$content = preg_replace( '/(<[^>]+) style=".*?"/i', '$1', $content );
			$content = preg_replace( "/(<[^>]+) style='.*?'/i", '$1', $content );
				
			// strip style tags
			$content = preg_replace( '/<style\\b[^>]*>(.*?)<\\/style>/s', '', $content);
			
			return $content;
		}
			
		public static function convert_smart_quotes( $content ) {
			$string = htmlentities( $content );
			$string = mb_convert_encoding( $string, 'HTML-ENTITIES', 'utf-8' );
			$string = htmlspecialchars_decode( utf8_decode ( htmlentities( $string, ENT_COMPAT, 'utf-8', false ) ) );
				
			$s = array(
			    chr(145) => "'",
			    chr(146) => "'",
			    chr(147) => '"',
			    chr(148) => '"',
			    chr(151) => '-',
			    's&#169;' => '�',
				'&#174;' => '�',
				'&#153;' => '�', //&trade;
				'���' => '"', // left side double smart quote
				'��' => '"', // right side double smart quote
				'���' => "'", // left side single smart quote
				'�۪' => "'", // right side single smart quote
				'��?' => '...', // elipsis
				'���' => '-', // em dash
				'���' => '-', // en dash
			);
				
			return strtr( $string, $s );
		}
			
		public static function remove_empty_tags( $content ) {
			return preg_replace( '/<(\w+)\b(?:\s+[\w\-.:]+(?:\s*=\s*(?:"[^"]*"|"[^"]*"|[\w\-.:]+))?)*\s*\/?>\s*<\/\1\s*>/', '', $content );
		}
	}
	
	WP_CLI::add_command( 'cleaner', 'Post_Cleaner' );
	
endif;