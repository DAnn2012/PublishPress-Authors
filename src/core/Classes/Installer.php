<?php
/**
 * @package PublishPress Authors
 * @author  PublishPress
 *
 * Copyright (C) 2018 PublishPress
 *
 * This file is part of PublishPress Authors
 *
 * PublishPress Authors is free software: you can redistribute it
 * and/or modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation, either version 3 of the License,
 * or (at your option) any later version.
 *
 * PublishPress is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with PublishPress.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace MultipleAuthors\Classes;

use MultipleAuthors\Classes\Objects\Author;
use WP_Role;

class Installer
{
    /**
     * Runs methods when the plugin is running for the first time.
     *
     * @param string $currentVersion
     */
    public static function runInstallTasks($currentVersion)
    {
        // Do not execute the post_author migration to post terms if Co-Authors Plus is activated.
        if (!isset($GLOBALS['coauthors_plus']) || empty($GLOBALS['coauthors_plus'])) {
            self::createAuthorTermsForLegacyCoreAuthors();
            self::createAuthorTermsForPostsWithLegacyCoreAuthors();
        }

        self::addDefaultCapabilitiesForAdministrators();
        self::addEditPostAuthorsCapabilitiesToRoles();
        self::flushRewriteRules();

        /**
         * @param string $currentVersion
         */
        do_action('pp_authors_install', $currentVersion);
    }

    /**
     * Runs methods when the plugin is being upgraded to a most recent version.
     *
     * @param string $currentVersions
     */
    public static function runUpgradeTasks($currentVersions)
    {
        if (version_compare($currentVersions, '2.0.2', '<')) {
            // Do not execute the post_author migration to post terms if Co-Authors Plus is activated.
            if (!isset($GLOBALS['coauthors_plus']) || empty($GLOBALS['coauthors_plus'])) {
                self::createAuthorTermsForLegacyCoreAuthors();
                self::createAuthorTermsForPostsWithLegacyCoreAuthors();
            }
        }

        if (version_compare($currentVersions, '3.6.0', '<')) {
            self::addEditPostAuthorsCapabilitiesToRoles();
        }

        /**
         * @param string $previousVersion
         */
        do_action('pp_authors_upgrade', $currentVersions);

        self::addDefaultCapabilitiesForAdministrators();
        self::flushRewriteRules();
    }

    public static function getUsersAuthorsWithNoAuthorTerm($args = null)
    {
        global $wpdb;

        if (!isset($args['post_type'])) {
            $enabledPostTypes = Utils::get_enabled_post_types();

            $args['post_type'] = $enabledPostTypes;
        }

        $defaults   = [
            'post_type'         => 'post',
            'posts_per_page'    => 300,
            'paged'             => 1,
        ];
        $parsedArgs = wp_parse_args($args, $defaults);


        if (!is_array($parsedArgs['post_type'])) {
            $parsedArgs['post_type'] = [esc_sql($parsedArgs['post_type'])];
        }

        $parsedArgs['post_type'] = array_map('esc_sql', $parsedArgs['post_type']);

        $parsedArgs['posts_per_page'] = (int)$parsedArgs['posts_per_page'];

        $parsedArgs['paged'] = (int)$parsedArgs['paged'];
        $parsedArgs['paged'] = $parsedArgs['paged'] * $parsedArgs['posts_per_page'] - $parsedArgs['posts_per_page'];

        return wp_list_pluck(
            $wpdb->get_results(
                "
                SELECT DISTINCT
                    p.post_author AS ID
                FROM
                    {$wpdb->posts} AS p
                WHERE
                    p.post_author NOT IN(
                        SELECT DISTINCT
                            meta_value FROM {$wpdb->termmeta} AS tm
                            LEFT JOIN {$wpdb->term_taxonomy} AS tt ON (tm.term_id = tt.term_id)
                        WHERE
                            meta_key = 'user_id'
                            AND tt.taxonomy = 'author'
                    )
                    AND p.post_author <> 0
                    AND p.post_type IN ('" . implode('\',\'', $parsedArgs['post_type']) . "')
                    AND p.post_status NOT IN ('trash')
                LIMIT {$parsedArgs['paged']}, {$parsedArgs['posts_per_page']}
                "
            ),
            'ID'
        );
    }

    /**
     * Creates terms for users found as authors in the content.
     */
    public static function createAuthorTermsForLegacyCoreAuthors()
    {
        // Get a list of authors (users) from the posts which has no terms.
        $users = self::getUsersAuthorsWithNoAuthorTerm();

        // Check if the authors have a term. If not, create one.
        if (!empty($users)) {
            foreach ($users as $userId) {
                Author::create_from_user($userId);
            }
        }
    }

    public static function getPostsWithoutAuthorTerms($args = null)
    {
        global $wpdb;

        if (!isset($args['post_type'])) {
            $enabledPostTypes = Utils::get_enabled_post_types();

            $args['post_type'] = $enabledPostTypes;
        }

        $defaults   = [
            'post_type'         => 'post',
            'order'             => 'ASC',
            'orderby'           => 'ID',
            'posts_per_page'    => 300,
            'paged'             => 1,
        ];
        $parsedArgs = wp_parse_args($args, $defaults);


        if (!is_array($parsedArgs['post_type'])) {
            $parsedArgs['post_type'] = [esc_sql($parsedArgs['post_type'])];
        }

        $parsedArgs['post_type'] = array_map('esc_sql', $parsedArgs['post_type']);

        $parsedArgs['order'] = strtoupper($parsedArgs['order']) === 'DESC' ? 'DESC' : 'ASC';

        $parsedArgs['orderby'] = esc_sql($parsedArgs['orderby']);

        $parsedArgs['posts_per_page'] = (int)$parsedArgs['posts_per_page'];

        $parsedArgs['paged'] = (int)$parsedArgs['paged'];
        $parsedArgs['paged'] = $parsedArgs['paged'] * $parsedArgs['posts_per_page'] - $parsedArgs['posts_per_page'];

        return $wpdb->get_results(
        "
            SELECT
                p.*
            FROM
                {$wpdb->posts} AS p
                LEFT JOIN (
                    SELECT
                        tr.object_id, tr.term_taxonomy_id
                    FROM
                        {$wpdb->term_relationships} AS tr
                        INNER JOIN {$wpdb->term_taxonomy} AS tt ON (tr.term_taxonomy_id = tt.term_taxonomy_id)
                    WHERE
                        tt.taxonomy = 'author') AS str ON (str.object_id = p.ID
                )
            WHERE
                p.post_type IN ('" . implode('\',\'', $parsedArgs['post_type']) . "')
                AND p.post_status NOT IN('trash')
                AND str.term_taxonomy_id IS NULL
            ORDER BY {$parsedArgs['orderby']} {$parsedArgs['order']}
            LIMIT {$parsedArgs['paged']}, {$parsedArgs['posts_per_page']}
            "
        );
    }

    /**
     * Add author term for posts which only have the post_author.
     */
    public static function createAuthorTermsForPostsWithLegacyCoreAuthors()
    {
        $postsToUpdate = self::getPostsWithoutAuthorTerms();

        if (!empty($postsToUpdate)) {
            foreach ($postsToUpdate as $postData) {
                $author = Author::get_by_user_id($postData->post_author);

                if (!is_object($author)) {
                    $author = Author::create_from_user($postData->post_author);
                }

                if (is_object($author)) {
                    $authors = [$author];

                    Utils::set_post_authors_name_meta($postData->ID, $authors);
                    Utils::sync_post_author_column($postData->ID, $authors);

                    $authors = wp_list_pluck($authors, 'term_id');

                    wp_add_object_terms($postData->ID, $authors, 'author');
                }
            }
        }
    }

    private static function addDefaultCapabilitiesForAdministrators()
    {
        $role = get_role('administrator');
        $role->add_cap('ppma_manage_authors');
        $role->add_cap('manage_options');
        $role->add_cap('ppma_edit_post_authors');
    }

    /**
     * Flushes the permalinks rules.
     */
    protected static function flushRewriteRules()
    {
        global $wp_rewrite;

        if (is_object($wp_rewrite)) {
            $wp_rewrite->flush_rules();
        }
    }

    private static function addEditPostAuthorsCapabilitiesToRoles()
    {
        $cap   = 'ppma_edit_post_authors';
        $roles = [
            'author',
            'editor',
            'contributor',
        ];

        foreach ($roles as $roleNmae) {
            $role = get_role($roleNmae);
            if ($role instanceof WP_Role) {
                $role->add_cap($cap);
            }
        }
    }
}
