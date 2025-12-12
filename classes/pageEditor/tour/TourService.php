<?php

declare(strict_types=1);

namespace Kpg\Plugins\LearnplacesMap\PageEditor\Tour;

use ILIAS\UI\Component\Input\Container\Form\Standard;
use ILIAS\UI\Factory;
use ILIAS\UI\Component\Component;
use ILIAS\Data\URI;
use ILIAS\DI\Container;
use ILIAS\UI\Component\Tree\Tree;

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
    public function addItemButton(string $action): array
    {
        $modal = $this->factory->modal()->roundtrip(
            'Lernort hinzufügen',
            $this->getExpandableTreeUI(),
            [],
            $action,
        );

        $button = $this->factory->button()->standard('Lernort hinzufügen', $action)->withonClick($modal->getShowSignal());

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

    public function getTourTable(string $sort_action): Component
    {
        #$df = new \ILIAS\Data\Factory();
        $request = $this->dic->http()->request();
        #$request_wrapper = $this->dic->http()->wrapper()->query();

        $columns = [
            'title' => $this->factory->table()->column()->text("Title"),
        ];

        // Sammelaktionen -> edit
        /*$url = $this->ctrl->getLinkTargetByClass(ilLearningVideoVideoGUI::class, ilLearningVideoVideoGUI::SHOW_CONTENT);
        $url_builder = new URLBuilder($df->uri(ILIAS_HTTP_PATH . '/' . $url));
        list($url_builder, $action_parameter_token, $row_id_token) = $url_builder->acquireParameters(
            ['video'],
            "table_action",
            "id"
        );
        $actions['edit'] = $this->factory->table()->action()->single(
            $this->lng->txt('edit'),
            $url_builder,
            $row_id_token
        );*/

        // Sammelaktionen -> löschen
        /*$url = $this->ctrl->getLinkTargetByClass(self::class, self::DELETE_VIDEOS_CONFIRMATION);
        $url_builder = new URLBuilder($df->uri(ILIAS_HTTP_PATH . '/' . $url));
        list($url_builder, $action_parameter_token, $row_id_token) = $url_builder->acquireParameters(
            ['delete'],
            "table_action",
            "ids"
        );
        $actions['delete'] = $this->factory->table()->action()->multi(
            $this->lng->txt('delete'),
            $url_builder,
            $row_id_token
        )->withAsync();*/

        #$url = $this->ctrl->getLinkTargetByClass(self::class, self::SAVE_ORDER);
        $url = '#';
        $target = new URI(ILIAS_HTTP_PATH . '/' . $sort_action);

        return $this->factory->table()->ordering(
            '',
            $columns,
            new tourTableDataRetrieval(),
            $target
        )
            #->withActions($actions)
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
            ->withEnvironment([
             'icon_factory' => $this->factory->symbol()->icon(),
            ])
            ->withData($all_learnplaces_in_course);
    }
}
