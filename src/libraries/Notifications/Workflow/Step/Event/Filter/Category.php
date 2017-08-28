<?php
/**
 * @package     PublishPress\Notifications
 * @author      PressShack <help@pressshack.com>
 * @copyright   Copyright (C) 2017 PressShack. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.0.0
 */

namespace PublishPress\Notifications\Workflow\Step\Event\Filter;

class Category extends Base implements Filter_Interface {

	const META_KEY_CATEGORY = '_psppno_whencategory';

	/**
	 * Function to render and returnt the HTML markup for the
	 * Field in the form.
	 *
	 * @return string
	 */
	public function render() {
		echo $this->get_service( 'twig' )->render(
			'workflow_filter_multiple_select.twig',
			[
				'name'    => "publishpress_notif[{$this->step_name}_filters][category]",
				'id'      => "publishpress_notif_{$this->step_name}_filters_category",
				'options' => $this->get_options(),
				'labels'  => [
					'label' => esc_html__( 'Category', 'publishpress' ),
					'any'   => esc_html__( '- any category -', 'publishpress' ),
				]
			]
		);
	}

	/**
	 * Returns a list of post types in the options format
	 *
	 * @return array
	 */
	protected function get_options() {
		$categories = get_categories([
			'orderby'      => 'name',
			'order'        => 'ASC',
			'hide_empty'   => false,
			'hierarchical' => true,
		]);

		$metadata = (array) $this->get_metadata( static::META_KEY_CATEGORY );

		$options = [];
		foreach ( $categories as $category ) {
			$options[] = [
				'value'    => $category->slug,
				'label'    => $category->name,
				'selected' => in_array( $category->slug, $metadata ),
			];
		}

		return $options;
	}

	/**
	 * Function to save the metadata from the metabox
	 *
	 * @param int     $id
	 * @param WP_Post $post
	 */
	public function save_metabox_data( $id, $post ) {
		if ( ! isset( $_POST['publishpress_notif']["{$this->step_name}_filters"]['category'] ) ) {
			$values = [];
		} else {
			$values = $_POST['publishpress_notif']["{$this->step_name}_filters"]['category'];
		}

		$this->update_metadata_array( $id, static::META_KEY_CATEGORY, $values, true );
	}

	/**
	 * Filters and returns the arguments for the query which locates
	 * workflows that should be executed.
	 *
	 * @param array $query_args
	 * @param array $action_args
	 * @return array
	 */
	public function get_run_workflow_query_args( $query_args, $action_args ) {

		$categories = wp_get_post_terms( $action_args['post']->ID, 'category' );
		$category_ids = [];

		if ( ! empty( $categories ) ) {
			foreach ( $categories as $category ) {
				$category_ids[] = $category->slug;
			}
		}
		$category_ids = implode( ',', $category_ids );

		$query_args['meta_query'][] = [
			'relation' => 'OR',
			[
				'key'     => static::META_KEY_CATEGORY,
				'value'   => $category_ids,
				'compare' => 'IN',
			],
			[
				'key'     => static::META_KEY_CATEGORY,
				'value'   => 'all',
				'type'    => 'CHAR',
				'compare' => '=',
			],
		];

		return parent::get_run_workflow_query_args( $query_args, $action_args );
	}
}