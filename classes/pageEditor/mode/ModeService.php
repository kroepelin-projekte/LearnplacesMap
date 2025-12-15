<?php

declare(strict_types=1);

namespace Kpg\Plugins\LearnplacesMap\PageEditor\Mode;

use ILIAS\UI\Component\Input\Container\Form\Standard;
use ILIAS\UI\Factory;
use ILIAS\DI\Container;
use ilLearnplacesMapPluginGUI;
use Kpg\Plugins\LearnplacesMap\PageEditor\Tour\TourService;


class ModeService
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

        $description_input = $this->dic->ui()->factory()->input()->field()->textarea('Beschreibung')
            ->withRequired(true);

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
