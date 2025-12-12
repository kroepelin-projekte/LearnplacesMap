<?php

declare(strict_types=1);

class ilLearnplacesMapPlugin extends ilPageComponentPlugin
{
    public const PLUGIN_ID = "lmap";

    public function isValidParentType(string $a_type): bool
    {
        return (bool) self::isInCourseContext();
    }

    /**
     * Checks if the current object is in a course context
     * Returns the course ref_id if it is, false otherwise.
     */
    public static function isInCourseContext(): int|false
    {
        global $DIC;

        if (!$DIC->http()->wrapper()->query()->has('ref_id')) {
            return false;
        }

        $ref_id = $DIC->http()->wrapper()->query()->retrieve('ref_id', $DIC->refinery()->kindlyTo()->int());
        $object_path = $DIC->repositoryTree()->getNodePath($ref_id, ROOT_FOLDER_ID);

        $courses = array_filter(
            $object_path,
            function ($node) {
                return $node['type'] === 'crs';
            },
        );

        return current($courses)['child'] ?? false;
    }

    public function onDelete(
        array $a_properties,
        string $a_plugin_version,
        bool $move_operation = false
    ): void {
        if ($move_operation) {
            return;
        }
        // todo delete
    }
}