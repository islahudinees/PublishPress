<?php
/**
 * @package     PublishPress\Notifications
 * @author      PublishPress <help@publishpress.com>
 * @copyright   Copyright (c) 2018 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.0.0
 */

namespace PublishPress\Notifications\Workflow\Step\Event_Content\Filter;

use PublishPress\Notifications\Workflow\Step\Event\Filter\Filter_Interface;
use PublishPress\Notifications\Workflow\Step\Event_Content\Taxonomy as Step_Taxonomy;

class Term extends Base implements Filter_Interface
{
    const META_KEY_TERM = '_psppno_whenterm';

    /**
     * Function to render and returnt the HTML markup for the
     * Field in the form.
     *
     * @return string
     */
    public function render()
    {
        echo $this->get_service('twig')->render(
            'workflow_filter_multiple_select.twig',
            [
                'name'    => "publishpress_notif[{$this->step_name}_filters][term]",
                'id'      => "publishpress_notif_{$this->step_name}_filters_term",
                'options' => $this->get_options(),
                'labels'  => [
                    'label' => esc_html__('Terms', 'publishpress'),
                ],
            ]
        );
    }

    /**
     * @param $a
     * @param $b
     */
    protected function sort_options($a, $b)
    {
        if ($a['label'] == $b['label']) {
            return 0;
        }

        return ($a['label'] < $b['label']) ? -1 : 1;
    }

    /**
     * Returns a list of post types in the options format
     *
     * @return array
     */
    protected function get_options()
    {
        $terms = get_terms(['hide_empty' => false]);

        $metadata = (array)$this->get_metadata(static::META_KEY_TERM);

        $options = [];
        foreach ($terms as $term) {
            $options[] = [
                'value'    => $term->term_id,
                'label'    => $term->taxonomy . '/' . $term->name,
                'selected' => in_array($term->term_id, $metadata),
            ];
        }

        usort($options, [$this, 'sort_options']);

        return $options;
    }

    /**
     * Function to save the metadata from the metabox
     *
     * @param int     $id
     * @param WP_Post $post
     */
    public function save_metabox_data($id, $post)
    {
        if ( ! isset($_POST['publishpress_notif']["{$this->step_name}_filters"]['term'])) {
            $values = [];
        } else {
            $values = $_POST['publishpress_notif']["{$this->step_name}_filters"]['term'];
        }

        $this->update_metadata_array($id, static::META_KEY_TERM, $values);
    }

    /**
     * Filters and returns the arguments for the query which locates
     * workflows that should be executed.
     *
     * @param array $query_args
     * @param array $action_args
     *
     * @return array
     */
    public function get_run_workflow_query_args($query_args, $action_args)
    {
        // If post is not set, we ignore.
        if ( ! isset($action_args['post']) || ! is_object($action_args['post'])) {
            return parent::get_run_workflow_query_args($query_args, $action_args);
        }

        $taxonomies = array_values(get_taxonomies());

        $terms    = wp_get_post_terms($action_args['post']->ID, $taxonomies);
        $term_ids = [];

        if ( ! empty($terms)) {
            foreach ($terms as $term) {
                $term_ids[] = $term->term_id;
            }
        }
        $term_ids = implode(',', $term_ids);

        $query_args['meta_query'][] = [
            'relation' => 'OR',
            // The filter is disabled
            [
                'key'     => Step_Taxonomy::META_KEY_SELECTED,
                'value'   => '0',
                'compare' => '=',
            ],
            // The filter is disabled
            [
                'key'     => Step_Taxonomy::META_KEY_SELECTED,
                'value'   => '',
                'compare' => '=',
            ],
            // The filter is disabled
            [
                'key'     => Step_Taxonomy::META_KEY_SELECTED,
                'value'   => '',
                'compare' => 'IS NULL',
            ],
            // The filter wasn't set yet
            [
                'key'     => Step_Taxonomy::META_KEY_SELECTED,
                'value'   => '',
                'compare' => 'NOT EXISTS',
            ],
            // The filter validates the value
            [
                'key'     => static::META_KEY_TERM,
                'value'   => $term_ids,
                'compare' => 'IN',
            ],
        ];

        return parent::get_run_workflow_query_args($query_args, $action_args);
    }
}
