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

class CollectionModel
{
    public function __construct(
        protected Container $dic,
        protected Factory $factory,
    ) {
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

    public function storeGroup(int $map_id, string $tag_name, bool $active, string $color, ?string $resource_id): void
    {
        $db = $this->dic->database();

        // Create record if not exists
        if (!$this->groupExists($map_id, $tag_name)) {
            $next_id = $db->nextId('kpg_lmap_collection');
            $this->dic->database()->manipulateF(
                "INSERT INTO kpg_lmap_collection (id, map_id, active, tag_name, color, resource_id) VALUES (%s, %s, %s, %s, %s, %s)",
                [
                    'integer',
                    'integer',
                    'integer',
                    'text',
                    'text',
                    'text',
                ],
                [
                    $next_id,
                    $map_id,
                    $active ? 1 : 0,
                    $tag_name,
                    $color,
                    $resource_id,
                ]
            );
        } else {
            $this->dic->database()->manipulateF(
                "UPDATE kpg_lmap_collection SET active = %s, color = %s, resource_id = %s WHERE map_id = %s AND tag_name = %s",
                [
                    'integer',
                    'text',
                    'text',
                    'integer',
                    'text',
                ],
                [
                    $active ? 1 : 0,
                    $color,
                    $resource_id,
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
            'resource_id' => null,
        ];
    }
}