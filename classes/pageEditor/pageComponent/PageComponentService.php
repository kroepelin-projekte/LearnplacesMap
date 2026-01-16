<?php

declare(strict_types=1);

namespace Kpg\Plugins\LearnplacesMap\PageEditor\PageComponent;

use ILIAS\UI\Component\Input\Container\Form\Standard;
use ILIAS\UI\Factory;
use ILIAS\DI\Container;
use ilLearnplacesMapPluginGUI;
use ilObject;
use Kpg\Plugins\LearnplacesMap\PageEditor\Tour\TourModel;
use Kpg\Plugins\LearnplacesMap\PageEditor\Collection\CollectionModel;

class PageComponentService
{
    public const MODE_TOUR = 'tour';
    public const MODE_COLLECTION = 'collection';

    public function __construct(
        protected Container $dic,
        protected Factory $factory,
    ) {
    }

    /**
     * @param string $mode
     * @param string $title
     * @param string $description
     * @return int
     */
    public function addMap(string $mode, string $title, string $description): int
    {
        $id = $this->dic->database()->nextId('kpg_lmap_map');
        $this->dic->database()->insert('kpg_lmap_map', [
            'id' => ['integer', $id],
            'mode' => ['text', $mode],
            'title' => ['text', $title],
            'description' => ['text', $description],
            'context_ref_id' => ['integer', \ilLearnplacesMapPlugin::getContext()],
        ]);
        return $id;
    }

    /**
     * @param int    $map_id
     * @param string $title
     * @param string $description
     * @return void
     */
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
    public function getInfo(int $map_id): array
    {
        if ($map_id < 0) {
            return [
                'title' => '',
                'description' => '',
                'context_ref_id' => null,
            ];
        }

        $db = $this->dic->database();
        $sql = $db->queryF(
            'SELECT title, description, context_ref_id FROM kpg_lmap_map WHERE id = %s',
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
            'context_ref_id' => (int) $map->context_ref_id,
        ];
    }

    /**
     * @param int $map_id
     * @param int $course_ref_id
     * @return void
     */
    public function updateCourseRefId(int $map_id, int $course_ref_id): void
    {
        $this->dic->database()->manipulateF(
            <<<SQL
            UPDATE kpg_lmap_map SET course_ref_id = %s WHERE id = %s
            SQL,
            [
                'integer',
                'integer',
            ],
            [
                $course_ref_id,
                $map_id,
            ]
        );

    }

    /**
     * @param int    $map_id
     * @param string $action
     * @return Standard
     */
    public function getMapUpdateForm(int $map_id, string $action): Standard
    {
        $map = $this->getInfo($map_id);

        $title = $this->factory->input()->field()->text($this->dic->language()->txt('title'))
            ->withMaxLength(400)
            ->withRequired(true)
            ->withValue($map['title']);
        $description = $this->factory->input()->field()->textarea($this->dic->language()->txt('description'))
            ->withMaxLimit(400)
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
            ['section' => $section],
        );
    }

    /**
     * @param int    $map_id
     * @param string $mode
     * @return void
     */
    public function deleteMap(int $map_id, string $mode): void
    {
        if ($mode === self::MODE_TOUR) {
            $tour_model = new TourModel($this->dic);
            $tour_model->deleteAllItems($map_id);
        } elseif ($mode === self::MODE_COLLECTION) {
            $collection_model = new CollectionModel($this->dic);
            $collection_model->deleteAllGroups($map_id);
        }

        $this->dic->database()->manipulateF(
            <<<SQL
            DELETE FROM kpg_lmap_map
            WHERE id = %s
            SQL,
            ['integer'],
            [$map_id],
        );
    }

    /**
     * @param string $form_action
     * @return Standard
     */
    public function getModeForm(string $form_action): Standard
    {
        $mode_radio_input = $this->dic->ui()->factory()->input()->field()->radio('Mode')
            ->withOption(self::MODE_TOUR, 'Tour')
            ->withOption(self::MODE_COLLECTION, 'Sammlung')
            ->withRequired(true);

        $title_input = $this->dic->ui()->factory()->input()->field()->text('Titel')
            ->withmaxLength(400)
            ->withRequired(true);

        $description_input = $this->dic->ui()->factory()->input()->field()->textarea('Beschreibung')->withMaxLimit(400);

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
            ['section' => $section],
        );
    }
}
