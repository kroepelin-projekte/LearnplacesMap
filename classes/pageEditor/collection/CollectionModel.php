<?php

declare(strict_types=1);

namespace Kpg\Plugins\LearnplacesMap\PageEditor\Collection;

use ILIAS\DI\Container;
use KPG\Learnplaces\container\PluginContainer;
use KPG\Learnplaces\persistence\repository\LearnplaceRepository;
use KPG\Learnplaces\persistence\dto\Configuration;
use ilObject;
use Kpg\Plugins\LearnplacesMap\PageEditor\Tour\TourModel;
use KPG\Learnplaces\persistence\dto\Location;

class CollectionModel
{
    private LearnplaceRepository $learnplace_service;

    public function __construct(
        protected Container $dic,
    ) {
        /** @var LearnplaceRepository $learnplace_service */
        $this->learnplace_service = PluginContainer::resolve(LearnplaceRepository::class);
    }

    /**
     * @param int $map_id
     * @return bool
     */
    public function hasItems(int $map_id): bool
    {
        $sql = $this->dic->database()->queryF(
            <<<SQL
            SELECT COUNT(*) AS count
            FROM kpg_lmap_collection
            WHERE map_id = %s
            SQL,
            ['integer'],
            [$map_id]
        );

        $item = $this->dic->database()->fetchObject($sql);
        return (bool) $item->count;
    }

    /**
     * @param int    $map_id
     * @param string $tag_name
     * @return void
     */
    public function deleteGroup(int $map_id, string $tag_name): void
    {
        $this->dic->database()->manipulateF(
            "DELETE FROM kpg_lmap_collection WHERE map_id = %s AND tag_name = %s",
            [
                'integer',
                'text',
            ],
            [
                $map_id,
                $tag_name,
            ]
        );
    }

    /**
     * @param int $map_id
     * @return void
     */
    public function deleteAllGroups(int $map_id): void
    {
        $this->dic->database()->manipulateF(
            <<<SQL
            DELETE FROM kpg_lmap_collection
            WHERE map_id = %s
            SQL,
            ['integer',],
            [$map_id,],
        );
    }

    /**
     * @return void
     */
    public function cleanupDeletedTagsFromCollectionMap(): void
    {
        $db = $this->dic->database();

        $res = $db->query("SELECT tags FROM xsrl_configuration");
        $active_tags = [];
        while ($row = $db->fetchAssoc($res)) {
            $tags = explode(',', (string) $row['tags']);
            foreach ($tags as $tag) {
                $trimmed = trim($tag);
                if ($trimmed !== '') {
                    // no duplicate entries
                    $active_tags[$trimmed] = true;
                }
            }
        }
        $active_tags_list = array_keys($active_tags);

        $res = $db->query("SELECT DISTINCT tag_name FROM kpg_lmap_collection");
        $tags_in_collection = [];
        while ($row = $db->fetchAssoc($res)) {
            $tags_in_collection[] = $row['tag_name'];
        }

        $tags_to_delete = array_diff($tags_in_collection, $active_tags_list);

        if (count($tags_to_delete) > 0) {
            $db->manipulate(
                "DELETE FROM kpg_lmap_collection WHERE " .
                $db->in('tag_name', $tags_to_delete, false, 'text')
            );
        }
    }

    /**
     * @param int    $map_id
     * @param string $tag_name
     * @param bool   $active
     * @param string $color
     * @return void
     */
    public function storeGroup(int $map_id, string $tag_name, bool $active, string $color): void
    {
        $db = $this->dic->database();

        // Create record if not exists
        if (!$this->groupExists($map_id, $tag_name)) {
            $next_id = $db->nextId('kpg_lmap_collection');
            $this->dic->database()->manipulateF(
                "INSERT INTO kpg_lmap_collection (id, map_id, active, tag_name, color) VALUES (%s, %s, %s, %s, %s)",
                [
                    'integer',
                    'integer',
                    'integer',
                    'text',
                    'text',
                ],
                [
                    $next_id,
                    $map_id,
                    $active ? 1 : 0,
                    $tag_name,
                    $color,
                ]
            );
        } else {
            $this->dic->database()->manipulateF(
                "UPDATE kpg_lmap_collection SET active = %s, color = %s WHERE map_id = %s AND tag_name = %s",
                [
                    'integer',
                    'text',
                    'integer',
                    'text',
                ],
                [
                    $active ? 1 : 0,
                    $color,
                    $map_id,
                    $tag_name,
                ]
            );
        }
    }

    /**
     * @param int    $map_id
     * @param string $tag_name
     * @return bool
     */
    private function groupExists(int $map_id, string $tag_name): bool
    {
        $sql = $this->dic->database()->queryF(
            "SELECT COUNT(*) AS count FROM kpg_lmap_collection WHERE map_id = %s AND tag_name = %s",
            [
                'integer',
                'text',
            ],
            [
                $map_id,
                $tag_name,
            ]
        );

        return $this->dic->database()->fetchObject($sql)->count === 1;
    }

    /**
     * @param string $tag_name
     * @param int    $map_id
     * @return array|null
     */
    public function getGroup(string $tag_name, int $map_id): ?array
    {
        $db = $this->dic->database();
        $sql = $db->queryF(
            "SELECT * FROM kpg_lmap_collection WHERE tag_name = %s AND map_id = %s",
            ['text', 'integer'],
            [$tag_name, $map_id],
        );

        if ($rec = $db->fetchAssoc($sql)) {
            return $rec;
        }

        // return default
        return [
            'active' => false,
            'color' => '#66f529',
        ];
    }

    /**
     * @param int $map_id
     * @return \Generator
     */
    public function getLearnplacesOfCollection(int $map_id): \Generator
    {
        $db = $this->dic->database();

        $sql = $db->queryF(
            <<<SQL
            SELECT m.title, m.description, m.context_ref_id, c.tag_name, c.active, c.color
            FROM kpg_lmap_map AS m
                JOIN kpg_lmap_collection AS c ON m.id = c.map_id
            WHERE c.map_id = %s AND c.active = 1
            SQL,
            ['integer',],
            [$map_id],
        );

        $rendered_learnplaces = [];
        while ($row = $db->fetchAssoc($sql)) {
            $context_ref_id = (int) $row['context_ref_id'];
            $tag_name = $row['tag_name'];
            $color = $row['color'];

            $learnplaces = $this->dic->repositoryTree()->getSubTree(
                $this->dic->repositoryTree()->getNodeData($context_ref_id),
                false,
                ['xsrl']
            );

            foreach ($learnplaces as $learnplace_ref_id) {
                $obj_id = ilObject::_lookupObjectId($learnplace_ref_id);

                if (!ilObject::_exists($obj_id)) {
                    continue;
                }

                $object_learnplace = $this->learnplace_service->findByObjectId($obj_id);

                /** @var Configuration $configuration */
                $configuration = $object_learnplace->getConfiguration();

                if (!$configuration->isOnline()) {
                    continue;
                }

                $tags_string = $object_learnplace->getConfiguration()->getTags();
                $tags_array = ($tags_string !== "")
                    ? array_map('trim', explode(',', $tags_string))
                    : [];

                if (!in_array($tag_name, $tags_array)) {
                    continue;
                }

                if (!isset($rendered_learnplaces[$learnplace_ref_id])) {
                    $rendered_learnplaces[$learnplace_ref_id] = 1;
                } else {
                    $rendered_learnplaces[$learnplace_ref_id]++;
                }

                yield [
                    'ref_id' => $learnplace_ref_id,
                    'object' => $object_learnplace,
                    'color' => $color,
                    'tag_name' => $tag_name,
                    'render_index' => $rendered_learnplaces[$learnplace_ref_id],
                ];
            }
        }
    }

    /**
     * Retrieves a generator for user-specific collections and their corresponding learnplaces.
     *
     * This function gathers all course and group object IDs where the user is a member,
     * extracts the unique context reference IDs for those objects, and fetches relevant
     * data about the collections associated with these context references. Each collection
     * data includes details of its associated learnplaces, such as their location, visited
     * status, and additional metadata.
     *
     * @return \Generator An iterable generator providing collection data, where each item
     *                     contains collection details and its associated learnplaces.
     *
     * @throws \ilDatabaseException If any database query fails during collection or learnplace data retrieval.
     */
    public function getCollectionsOfUser(): \Generator
    {
        $db = $this->dic->database();
        $tour_model = new TourModel($this->dic);

        // Get all crs and grp obj_ids wher user is member
        $assigned_objects = \ilParticipants::_getMembershipByType(
            $this->dic->user()->getId(),
            ['crs', 'grp'],
            false,
        );

        // Get all context_ref_ids of assigned objects
        $all_context_ref_ids = [];
        foreach ($assigned_objects as $object_obj_id) {
            $all_context_ref_ids = array_merge($all_context_ref_ids, \ilObject::_getAllReferences($object_obj_id));
        }
        $all_context_ref_ids = array_unique($all_context_ref_ids);

        // Fetch all collection maps of user
        $in_condition = $db->in('context_ref_id', $all_context_ref_ids, false, 'integer');
        $res = $db->query(
            <<<SQL
            SELECT m.id, m.context_ref_id, m.title, m.description
            FROM kpg_lmap_map AS m
            WHERE $in_condition AND m.mode = 'collection'
            ORDER BY m.id ASC
            SQL
        );

        $collection_data = [];
        while ($row = $db->fetchAssoc($res)) {
            $collection_data = [
                'map_id' => (int) $row['id'],
                'title' => $row['title'],
                'description' => nl2br($row['description']),
                'context_ref_id' => (int) $row['context_ref_id'],
                'collection_learnplaces' => []
            ];

            $learnplaces = $this->getLearnplacesOfCollection((int) $row['id']);
            foreach ($learnplaces as $learnplace_item) {
                $learnplace_ref_id = $learnplace_item['ref_id'];
                $learnplace = $learnplace_item['object'];
                /** @var Location $location */
                $location = $learnplace->getLocation();

                // Get visited status
                $is_visited = $tour_model->isVisited($this->dic->user()->getId(), $learnplace->getId());

                $collection_data['collection_learnplaces'][] = [
                    'id' => $learnplace->getId(),
                    'title' => ilObject::_lookupTitle($learnplace->getObjectId()),
                    'latitude' => $location->getLatitude(),
                    'longitude' => $location->getLongitude(),
                    'radius' => $location->getRadius(),
                    'visited' => $is_visited,
                    'url' => ILIAS_HTTP_PATH . '/go/xsrl/' . $learnplace_ref_id,
                    'color' => $learnplace_item['color'],
                    'tag_name' => $learnplace_item['tag_name'],
                    'render_index' => $learnplace_item['render_index'],
                ];
            }

            yield $collection_data;
        }
    }

    /**
     * @param int $map_id
     * @return array|null
     */
    public function getCollection(int $map_id): ?array
    {
        $db = $this->dic->database();
        $res = $db->queryF(
            <<<SQL
            SELECT m.id, m.context_ref_id, m.title, m.description
            FROM kpg_lmap_map AS m
            WHERE m.id = %s AND m.mode = 'collection'
            ORDER BY m.id ASC
            SQL,
            ['integer'],
            [$map_id]
        );

        if (!$row = $db->fetchAssoc($res)) {
            return null;
        }

        $context_ref_id = (int) $row['context_ref_id'];

        if (!$this->dic->rbac()->system()->checkAccess(
            'read',
            $context_ref_id,
            \ilObject::_lookupType($context_ref_id, true)
        )) {
            return null;
        }

        $collection_data = [
            'map_id' => (int) $row['id'],
            'context_ref_id' => $context_ref_id,
            'title' => $row['title'],
            'description' => nl2br($row['description']),
            'collection_learnplaces' => []
        ];

        $tour_model = new TourModel($this->dic);

        $learnplaces = $this->getLearnplacesOfCollection($map_id);
        foreach ($learnplaces as $learnplace_item) {
            $learnplace_ref_id = $learnplace_item['ref_id'];
            $learnplace = $learnplace_item['object'];
            /** @var Location $location */
            $location = $learnplace->getLocation();

            // Get visited status of current user
            $is_visited = $tour_model->isVisited($this->dic->user()->getId(), $learnplace->getId());

            $collection_data['collection_learnplaces'][] = [
                'id' => $learnplace->getId(),
                'title' => \ilObject::_lookupTitle($learnplace->getObjectId()),
                'latitude' => $location->getLatitude(),
                'longitude' => $location->getLongitude(),
                'radius' => $location->getRadius(),
                'visited' => $is_visited ? 'true' : 'false',
                'url' => ILIAS_HTTP_PATH . '/go/xsrl/' . $learnplace_ref_id,
                'color' => $learnplace_item['color'],
                'tag_name' => $learnplace_item['tag_name'],
                'render_index' => $learnplace_item['render_index'],
            ];
        }

        return $collection_data;
    }

    public function getTags(int $map_id): array
    {
        $db = $this->dic->database();
        $res = $db->queryF(
            <<<SQL
            SELECT map_id, tag_name, active, color
            FROM kpg_lmap_collection
            WHERE active = 1 AND map_id = %s
            SQL,
            ['integer'],
            [$map_id]
        );
        $tag_names = [];
        while ($row = $db->fetchAssoc($res)) {
            $tag_names[] = [
                'tag_name' => $row['tag_name'],
                'active' => (bool) $row['active'],
                'color' => $row['color'],
            ];
        }
        return $tag_names;
    }
}
