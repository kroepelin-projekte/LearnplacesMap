<?php

declare(strict_types=1);

namespace Kpg\Plugins\LearnplacesMap\PageEditor\Tour;

use ILIAS\UI\Component\Input\Container\Form\Standard;
use ILIAS\UI\Factory;
use ILIAS\UI\Component\Component;
use ILIAS\Data\URI;
use ILIAS\DI\Container;
use ILIAS\UI\Component\Tree\Tree;
use ILIAS\UI\URLBuilder;

class TourService
{
    public function __construct(
        protected Factory $factory,
        protected Container $dic,
    ) {
    }

    /**
     * @param string $action
     * @return array{button: Component, modal: Component}
     */
    public function addItemButton(): array
    {
        $modal = $this->factory->modal()->roundtrip(
            'Lernort hinzufügen',
            $this->getExpandableTreeUI(),
            [
                'ref_id' => $this->factory->input()->field()->hidden()->withAdditionalOnLoadCode(
                    fn (string $id): string =>
                        <<<JS
                        const hidden_input = document.getElementById('$id');
                        document.addEventListener('change', (e) => {
                            if (e.target.matches('input[name="learnplace"]')) {
                                hidden_input.value = e.target.value
                            }
                        })
                        JS
                )
            ],
            $this->dic->ctrl()->getLinkTargetByClass(\ilLearnplacesMapTourGUI::class, \ilLearnplacesMapTourGUI::TOUR_ADD_ITEM),
        );

        $button = $this->factory->button()->standard('Lernort hinzufügen', '#')->withonClick($modal->getShowSignal());

        return [
            'button' => $button,
            'modal' => $modal,
        ];
    }

    public function getTourForm(string $action): Standard
    {
        $mode_radio_input = $this->factory->input()->field()->text('Tour...', '');

        $section = $this->factory->input()->field()->section(
            [
                'mode' => $mode_radio_input,
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

    public function getTourTable(int $map_id): Component
    {
        $this->dic->ui()->mainTemplate()->addInlineCss('.c-table-data__positioninput label { display: none; }');
        $df = new \ILIAS\Data\Factory();
        $request = $this->dic->http()->request();

        $columns = [
            'ref_id' => $this->factory->table()->column()->text("REF ID"),
            'title' => $this->factory->table()->column()->text("Title"),
        ];

        // Sammelaktionen -> löschen
        $url = $this->dic->ctrl()->getLinkTargetByClass(\ilLearnplacesMapTourGUI::class, \ilLearnplacesMapTourGUI::TOUR_DELETE_ITEM);
        $url_builder = new URLBuilder($df->uri(ILIAS_HTTP_PATH . '/' . $url));
        list($url_builder, $action_parameter_token, $row_id_token) = $url_builder->acquireParameters(
            ['delete'],
            "table_action",
            "ids"
        );
        $actions['delete'] = $this->factory->table()->action()->multi(
            $this->dic->language()->txt('delete'),
            $url_builder,
            $row_id_token
        );

        $url = $this->dic->ctrl()->getLinkTargetByClass(\ilLearnplacesMapTourGUI::class, \ilLearnplacesMapTourGUI::TOUR_SAVE_ORDER);
        $target = new URI(ILIAS_HTTP_PATH . '/' . $url);

        return $this->factory->table()->ordering(
            '',
            $columns,
            new tourTableDataRetrieval($map_id),
            $target
        )
            ->withActions($actions)
            ->withRequest($request);
    }

    public function getExpandableTreeUI(): Tree
    {
        $course_ref_id = \ilLearnplacesMapPlugin::isInCourseContext();

        $all_learnplaces_in_course = $this->dic->repositoryTree()->getSubTree(
            $this->dic->repositoryTree()->getNodeData($course_ref_id),
            true,
            ['xsrl']
        );

        return $this->factory->tree()->expandable("Lernorte", new RepositoryTreeRecursion($this->dic))
            /*->withEnvironment([
             'icon_factory' => $this->factory->symbol()->icon(),
            ])*/
            ->withData($all_learnplaces_in_course);
    }

    public function addItem(int $map_id, int $learnplace_ref_id): void
    {
        if ($this->itemExists($map_id, $learnplace_ref_id)) {
            $this->dic->ui()->mainTemplate()->setOnScreenMessage('failure', 'Dieser Lernort ist bereits in dieser Tour enthalten.', true);
            return;
        }

        $next_id = $this->dic->database()->nextId('kpg_lmap_tour');
        $this->dic->database()->insert('kpg_lmap_tour', [
            'id' => ['integer', $next_id],
            'map_id' => ['integer', $map_id],
            'learnplace_ref_id' => ['integer', $learnplace_ref_id],
            'position' => ['integer', $next_id],
        ]);
    }

    private function itemExists(int $map_id, int $learnplace_ref_id): bool
    {
        $sql = $this->dic->database()->queryF(
            <<<SQL
            SELECT COUNT(*) AS count
            FROM kpg_lmap_tour
            WHERE map_id = %s AND learnplace_ref_id = %s
            SQL,
            [
                'integer',
                'integer',
            ],
            [
                $map_id,
                $learnplace_ref_id,
            ]
        );

        $item = $this->dic->database()->fetchObject($sql);
        return (bool) $item->count;
    }

    /**
     * @param int $map_id
     * @param int[] $ids
     * @return void
     */
    public function deleteItems(int $map_id, array $ids): void
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if (!$ids) {
            return;
        }

        $condition = $this->dic->database()->in(
            'id',
            $ids,
            false,
            'integer',
        );

        $this->dic->database()->manipulateF(
            <<<SQL
            DELETE FROM kpg_lmap_tour
            WHERE {$condition} AND map_id = %s
            SQL,
            [
                'integer',
            ],
            [
                $map_id,
            ],
        );
    }

    /**
     * @param int $map_id
     * @return void
     */
    public function deleteAllItems(int $map_id): void
    {
        $this->dic->database()->manipulateF(
            <<<SQL
            DELETE FROM kpg_lmap_tour
            WHERE map_id = %s
            SQL,
            [
                'integer',
            ],
            [
                $map_id,
            ],
        );
    }

    public function updatePosition(int $id, int $new_position): void
    {
        $this->dic->database()->manipulateF(
            <<<SQL
            UPDATE kpg_lmap_tour SET position = %s WHERE id = %s
            SQL,
            [
                'integer',
                'integer',
            ],
            [
                $new_position,
                $id,
            ]
        );
    }
}
