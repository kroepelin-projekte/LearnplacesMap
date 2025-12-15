<?php

declare(strict_types=1);

namespace Kpg\Plugins\LearnplacesMap\PageEditor\PageComponent;

use ILIAS\UI\Component\Input\Container\Form\Standard;
use ILIAS\UI\Factory;
use ILIAS\DI\Container;
use ilLearnplacesMapPluginGUI;
use Kpg\Plugins\LearnplacesMap\PageEditor\Tour\TourService;


class PageComponentService
{
    public const MODE_TOUR = 'tour';
    public const MODE_COLLECTION = 'collection';

    public function __construct(
        protected Container $dic,
        protected Factory $factory,
    ) {
    }

    public function addMap(string $mode, string $title, string $description): int
    {
        $id = $this->dic->database()->nextId('kpg_lmap_map');
        $this->dic->database()->insert('kpg_lmap_map', [
            'id' => ['integer', $id],
            'mode' => ['text', $mode],
            'title' => ['text', $title],
            'description' => ['text', $description],
        ]);
        return $id;
    }

    public function updateMap(int $map_id, string $title, string $description): void
    {
        $this->dic->database()->manipulateF(
            <<<SQL
            UPDATE kpg_lmap_map SET title = %s, description = %s WHERE id = %s
            SQL,
            [
                'text',
                'text',
                'integer',
            ],
            [
                $title,
                $description,
                $map_id,
            ]
        );
    }

    /**
     * @return array{title: string, description: string}
     */
    private function getInfo(int $map_id): array
    {
        if ($map_id < 0) {
            return [
                'title' => '',
                'description' => '',
            ];
        }

        $db = $this->dic->database();
        $sql = $db->queryF(
            'SELECT title, description FROM kpg_lmap_map WHERE id = %s',
            [
                'integer',
            ],
            [
                $map_id,
            ]
        );
        $map = $db->fetchObject($sql);
        return [
            'title' => $map->title,
            'description' => $map->description,
        ];
    }

    public function getMapUpdateForm(int $map_id, string $action): Standard
    {
        $map = $this->getInfo($map_id);

        $title = $this->factory->input()->field()->text('Title')
            ->withRequired(true)
            ->withValue($map['title']);
        $description = $this->factory->input()->field()->text('Beschreibung')
            ->withValue($map['description']);

        $section = $this->factory->input()->field()->section(
            [
                'title' => $title,
                'description' => $description,
            ],
            'Tour'
        );

        return $this->factory->input()->container()->form()->standard(
            $action,
            [
                'section' => $section,
            ],
        );
    }

    public function deleteMap(int $map_id, string $mode): void
    {
        $tour_service = new TourService($this->dic, $this->factory);

        if ($mode === self::MODE_TOUR) {
            $tour_service->deleteAllItems($map_id);

            $this->dic->database()->manipulateF(
                <<<SQL
            DELETE FROM kpg_lmap_map
            WHERE id = %s
            SQL,
                [
                    'integer',
                ],
                [
                    $map_id,
                ],
            );
        } elseif ($mode === self::MODE_COLLECTION) {
            // todo delete
        }
    }

    public function getModeForm(string $form_action): Standard
    {
        $mode_radio_input = $this->dic->ui()->factory()->input()->field()->radio('Mode')
            ->withOption(self::MODE_TOUR, 'Tour')
            ->withOption(self::MODE_COLLECTION, 'Sammlung')
            ->withRequired(true);

        $title_input = $this->dic->ui()->factory()->input()->field()->text('Titel')
            ->withRequired(true);

        $description_input = $this->dic->ui()->factory()->input()->field()->textarea('Beschreibung');

        $section = $this->dic->ui()->factory()->input()->field()->section(
            [
                'title' => $title_input,
                'description' => $description_input,
                'mode' => $mode_radio_input,
            ],
            'Karten Typ'
        );

        return $this->dic->ui()->factory()->input()->container()->form()->standard(
            $form_action,
            [
                'section' => $section,
            ],
        );
    }
}
