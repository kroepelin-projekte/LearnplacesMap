<?php

declare(strict_types=1);

namespace Kpg\Plugins\LearnplacesMap\PageEditor\Collection;

use ILIAS\UI\Component\Input\Container\Form\Standard;
use ILIAS\UI\Factory;
use ILIAS\Data\URI;
use ILIAS\DI\Container;
use ILIAS\UI\Component\Tree\Tree;
use ILIAS\UI\URLBuilder;
use KPG\Learnplaces\container\PluginContainer;
use KPG\Learnplaces\persistence\repository\LearnplaceRepository;
use ILIAS\UI\Component\Table\DataRetrieval;
use ILIAS\UI\Component\Table\DataRowBuilder;
use ILIAS\Data\Range;
use ILIAS\Data\Order;
use ilLearnplacesMapCollectionGUI;
use ILIAS\UI\Component\Modal\Modal;
use ILIAS\UI\Component\Table\Table;

use function ILIAS\UI\examples\Toast\Standard\with_action;
use function Sabre\VObject\write;
use KPG\Learnplaces\persistence\dto\Configuration;
use ilObject;

class CollectionModel
{
    private LearnplaceRepository $learnplace_service;

    public function __construct(
        protected Container $dic,
    ) {
        /** @var LearnplaceRepository $learnplace_service  */
        $this->learnplace_service = PluginContainer::resolve(LearnplaceRepository::class);
    }

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

    public function getGroup(string $tag_name): ?array
    {
        $db = $this->dic->database();
        $sql = $db->queryF(
            "SELECT * FROM kpg_lmap_collection WHERE tag_name = %s",
            [
                'text',
            ],
            [
                $tag_name,
            ]
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

    public function getLearnplacesOfCollection(int $map_id): \Generator
    {
        $db = $this->dic->database();

        $sql = $db->queryF(
            'SELECT context_ref_id, tag_name, active, color FROM kpg_lmap_collection JOIN kpg_lmap_map ON kpg_lmap_map.id = kpg_lmap_collection.map_id WHERE map_id = %s',
            ['integer',],
            [$map_id],
        );

        $rendered_learnplaces = [];
        while ($row = $db->fetchAssoc($sql)) {
            if (!$row['active']) {
                continue;
            }
            $context_ref_id = (int) $row['context_ref_id'];
            $tag_name = $row['tag_name'];
            $color = $row['color'];

            $learnplaces = $this->dic->repositoryTree()->getSubTree($this->dic->repositoryTree()->getNodeData($context_ref_id), false, ['xsrl']);

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
                    'render_index' => $rendered_learnplaces[$learnplace_ref_id],
                ];
            }
        }
    }
}