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
use KPG\Learnplaces\persistence\dto\Configuration;
use KPG\Learnplaces\persistence\repository\LearnplaceRepository;
use KPG\Learnplaces\container\PluginContainer;

class TourView
{
    private LearnplaceRepository $learnplace_service;
    private TourModel $tour_model;

    public function __construct(
        protected Container $dic,
        protected Factory $factory,
        protected int $map_id,
    ) {
        /** @var LearnplaceRepository $learnplace_service  */
        $this->learnplace_service = PluginContainer::resolve(LearnplaceRepository::class);
        $this->tour_model = new TourModel($this->dic);
        // Cleanup deleted learnplaces from tour maps
        $this->tour_model->cleanupDeletedLearnplacesFromTourMaps();
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

    public function getTable(): Table
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
            new TableDataRetrieval($this->map_id),
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

    public function getMap(): string
    {
        $tpl = $this->dic->ui()->mainTemplate();
        $tpl->addJavaScript('Customizing/global/plugins/Services/COPage/PageComponent/LearnplacesMap/dist/bundle.js');

        $tour_map_data = $this->tour_model->getTourMap($this->map_id);

        if (!$tour_map_data) {
            return ' ';
        }

        $learnplaces_json = json_encode(['learnplaces' => $tour_map_data['tour_learnplaces']], JSON_THROW_ON_ERROR);

        $link_list = array_map(fn($item) => "<li><a href='{$item['url']}' target='_self'>{$item['title']}</a></li>", $tour_map_data['tour_learnplaces']);
        $html_link_list = implode('', $link_list);

        $content_html = <<<HTML
            <script type="application/json" data-learnplaces-tour="learnplaces-tour-{$this->map_id}">
            {$learnplaces_json}
            </script>
            <div class="learnplaces-tour-content">
                <div class="left-column">
                    <p>{$tour_map_data['description']}</p>
                    <div class="learnplaces-tour-links">
                        <h3>Lernorte</h3>
                        <ol>
                            $html_link_list
                        </ol>
                    </div>
                </div>
                <div class="right-colums">
                    <div class="learnplaces-tour-map">
                        <div id="map-{$this->map_id}" style="width:100%; height:300px"></div>
                    </div>
                </div>
            </div>
            HTML;

        return $this->dic->ui()->renderer()->render(
            $this->dic->ui()->factory()->panel()->standard(
                $tour_map_data['title'],
                $this->dic->ui()->factory()->legacy($content_html)
            )
        );
    }
}
