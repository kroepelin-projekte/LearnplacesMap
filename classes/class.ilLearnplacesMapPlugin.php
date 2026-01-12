<?php

declare(strict_types=1);

use Kpg\Plugins\LearnplacesMap\PageEditor\PageComponent\PageComponentService;
use Kpg\Plugins\LearnplacesMap\PageEditor\Tour\TourModel;
use Kpg\Plugins\LearnplacesMap\PageEditor\Collection\CollectionModel;

class ilLearnplacesMapPlugin extends ilPageComponentPlugin
{
    public const PLUGIN_ID = "lmap";

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

        // Course context
        foreach ($containers as $node) {
            if ($node['type'] === 'crs') {
                return $node['child'];
            }
        }

        // Group context
        return $containers[0]['child'];
    }

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

        $context_ref_id = $page_component_service->getInfo($map_id)['context_ref_id'];

        // Context has changed, delete all tours associated with this map
        if ($context_ref_id !== $new_context_ref_id) {
            $tour_model = new TourModel($DIC);
            $tour_model->deleteAllItems($map_id);

            $collection_model = new CollectionModel($DIC);
            $collection_model->deleteAllGroups($map_id);

            $page_component_service->updateCourseRefId($map_id, $new_context_ref_id);
        }
    }

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

    public function getTourModel(): TourModel
    {
        global $DIC;
        return new TourModel($DIC);
    }

    public function getCollectionModel(): CollectionModel
    {
        global $DIC;
        return new CollectionModel($DIC);
    }
}
