<?php

declare(strict_types=1);

use Kpg\Plugins\LearnplacesMap\PageEditor\PageComponent\PageComponentService;
use Kpg\Plugins\LearnplacesMap\PageEditor\Tour\TourModel;
use Kpg\Plugins\LearnplacesMap\PageEditor\Collection\CollectionModel;

class ilLearnplacesMapPlugin extends ilPageComponentPlugin
{
    public const PLUGIN_ID = "lmap";

    /**
     * @param string $a_type
     * @return bool
     */
    public function isValidParentType(string $a_type): bool
    {
        if (!\ilObjectPlugin::getPluginObjectByType('xsrl')->isActive()) {
            return false;
        }

        return (bool) self::getContext();
    }

    /**
     * Checks if the current object is in a group or a course
     * @return int|false Returns the ref_id of the course or group, or false if not in a course or group
     */
    public static function getContext(): int|false
    {
        global $DIC;

        if (!$DIC->http()->wrapper()->query()->has('ref_id')) {
            return false;
        }

        $ref_id = $DIC->http()->wrapper()->query()->retrieve('ref_id', $DIC->refinery()->kindlyTo()->int());
        $path = $DIC->repositoryTree()->getNodePath($ref_id, ROOT_FOLDER_ID);

        $containers = array_values(array_filter(
            $path,
            static fn(array $node) => in_array($node['type'], ['crs', 'grp'], true)
        ));

        // No context
        if ($containers === []) {
            return false;
        }

        // Group context
        foreach ($containers as $node) {
            if ($node['type'] === 'grp') {
                return $node['child'];
            }
        }

        // Course context
        return $containers[0]['child'];
    }

    /**
     * Handles the cloning process of a map.
     *
     * This method runs during the cloning of an object and manages context-related changes.
     * It checks for any changes in the context (such as a course or group) and performs
     * cleanup and updates as needed. If the context has changed, it deletes all tours
     * and groups associated with the map and updates the reference ID for the new context.
     *
     * @param array  $a_properties     Array containing the properties of the object being cloned,
     *                                 such as the map ID and mode.
     * @param string $a_plugin_version The version of the plugin being used.
     * @return void
     * @throws ilException Throws an exception if no valid context is found.
     */
    public function onClone(
        array &$a_properties,
        string $a_plugin_version
    ): void
    {
        global $DIC;
        $map_id = (int) $a_properties['id'];
        $mode = $a_properties['mode'];
        $page_component_service = new PageComponentService($DIC, $DIC->ui()->factory());

        $new_context_ref_id = self::getContext();
        if ($new_context_ref_id === false) {
            throw new ilException('No valid context');
        }

        $map_data = $page_component_service->getInfo($map_id);
        $mode = $map_data['mode'];
        $title = $map_data['title'];
        $description = $map_data['description'];
        $context_ref_id = $map_data['context_ref_id'];

        // Copy
        $new_map_id = $page_component_service->addMap($mode, $title, $description);
        $a_properties['id'] = (string) $new_map_id;

        // Context is the same
        if ($context_ref_id === $new_context_ref_id) {

            if ($mode === 'tour') {
                $tour_model = new TourModel($DIC);
                $tour_learnplaces = $tour_model->getTourMap($map_id)['tour_learnplaces'];
                foreach ($tour_learnplaces as $tour_learnplace) {
                    $tour_model->addItem($new_map_id, (int) $tour_learnplace['learnplace_ref_id']);
                }
            } else {
                $collection_model = new CollectionModel($DIC);
                $tags = $collection_model->getTags($map_id);
                foreach ($tags as $tag) {
                    $collection_model->storeGroup($new_map_id, $tag['tag_name'], $tag['active'], $tag['color']);
                }
            }
        }
    }

    /**
     * Handles the deletion of a map object with optional movement to a new context.
     *
     * @param array  $a_properties     Properties of the map, including the ID and mode.
     * @param string $a_plugin_version The version of the plugin currently in use.
     * @param bool   $move_operation   Indicates if the operation involves moving the map to a new context.
     *
     * @return void
     * @throws ilException If the context is invalid during a move operation.
     *
     */
    public function onDelete(
        array $a_properties,
        string $a_plugin_version,
        bool $move_operation = false
    ): void {
        global $DIC;
        $map_id = (int) $a_properties['id'];
        $mode = $a_properties['mode'];
        $page_component_service = new PageComponentService($DIC, $DIC->ui()->factory());

        if ($move_operation) {

            $new_context_ref_id = self::getContext();
            if ($new_context_ref_id === false) {
                throw new ilException('No valid context');
            }

            $context_ref_id = $page_component_service->getInfo($map_id)['context_ref_id'];

            // Context has changed, delete all tours associated with this map
            if ($context_ref_id !== $new_context_ref_id) {

                // Delete tour items when moving to another course
                $tour_model = new TourModel($DIC);
                $tour_model->deleteAllItems($map_id);

                $collection_model = new CollectionModel($DIC);
                $collection_model->deleteAllGroups($map_id);

                // Update course ref_id
                $page_component_service->updateCourseRefId($map_id, $new_context_ref_id);
            }

            return;
        }

        $page_component_service->deleteMap($map_id, $mode);
    }

    /**
     * Retrieves an instance of the TourModel.
     *
     * @return TourModel Returns a new instance of the TourModel class.
     */
    public function getTourModel(): TourModel
    {
        global $DIC;
        return new TourModel($DIC);
    }

    /**
     * Retrieves an instance of the CollectionModel.
     *
     * @return CollectionModel Returns a new instance of the CollectionModel class.
     */
    public function getCollectionModel(): CollectionModel
    {
        global $DIC;
        return new CollectionModel($DIC);
    }
}
