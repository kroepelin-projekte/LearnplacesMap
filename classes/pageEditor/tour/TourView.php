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
use ILIAS\UI\Component\Table\Table;

class TourView
{
    public function __construct(
        protected Container $dic,
        protected Factory $factory,
    ) {
    }

    /**
     * @param string $action
     * @return array{button: Component, modal: Component}
     */
    public function addItemModal(): array
    {
        $modal = $this->factory->modal()->roundtrip(
            'Lernort hinzufügen',
            $this->getRepositoryTree(),
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
        )->withCancelButtonLabel($this->dic->language()->txt('close'));;

        $button = $this->factory->button()->standard('Lernort hinzufügen', '#')->withonClick($modal->getShowSignal());

        return [
            'button' => $button,
            'modal' => $modal,
        ];
    }

    public function getTable(int $map_id): Table
    {
        $this->dic->ui()->mainTemplate()->addInlineCss('.c-table-data__positioninput label { display: none; }');

        // Sammelaktionen -> löschen
        $url = $this->dic->ctrl()->getLinkTargetByClass(\ilLearnplacesMapTourGUI::class, \ilLearnplacesMapTourGUI::TOUR_DELETE_ITEM);
        $url_builder = new URLBuilder(new URI(ILIAS_HTTP_PATH . '/' . $url));
        list($url_builder, $action_parameter_token, $row_id_token) = $url_builder->acquireParameters(
            ['delete'],
            "table_action",
            "ids"
        );
        $actions['delete'] = $this->factory->table()->action()->standard(
            $this->dic->language()->txt('delete'),
            $url_builder,
            $row_id_token
        );

        $url = $this->dic->ctrl()->getLinkTargetByClass(\ilLearnplacesMapTourGUI::class, \ilLearnplacesMapTourGUI::TOUR_SAVE_ORDER);
        $target = new URI(ILIAS_HTTP_PATH . '/' . $url);

        return $this->factory->table()->ordering(
            '',
            [
                'ref_id' => $this->factory->table()->column()->text("REF ID"),
                'title' => $this->factory->table()->column()->text("Title"),
            ],
            new TableDataRetrieval($map_id),
            $target
        )
            ->withActions($actions)
            ->withRequest($this->dic->http()->request());
    }

    public function getRepositoryTree(): Tree
    {
        $course_ref_id = \ilLearnplacesMapPlugin::getContext();

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
}
