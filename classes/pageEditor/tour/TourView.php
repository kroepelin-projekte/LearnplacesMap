<?php

declare(strict_types=1);

namespace Kpg\Plugins\LearnplacesMap\PageEditor\Tour;

use ILIAS\UI\Factory;
use ILIAS\UI\Component\Component;
use ILIAS\Data\URI;
use ILIAS\DI\Container;
use ILIAS\UI\Component\Tree\Tree;
use ILIAS\UI\URLBuilder;
use ILIAS\UI\Component\Table\Table;
use ilCtrlException;
use ilLearnplacesMapTourGUI;

class TourView
{
    private TourModel $tour_model;
    private \ilPlugin|\ilLearnplacesMapPlugin $plugin;

    public function __construct(
        protected Container $dic,
        protected Factory $factory,
        protected int $map_id,
    ) {
        $this->tour_model = new TourModel($this->dic);
        // Cleanup deleted learnplaces from tour maps
        $this->tour_model->cleanupDeletedLearnplacesFromTourMaps();
        $this->plugin = \ilObjectPlugin::getPluginObjectByType('lmap');
    }

    /**
     * @return array{button: Component, modal: Component}
     * @throws ilCtrlException
     */
    public function addItemModal(): array
    {
        $modal = $this->factory->modal()->roundtrip(
            $this->plugin->txt('add_learnplace'),
            $this->getRepositoryTree(),
            [
                'ref_id' => $this->factory->input()->field()->hidden()->withAdditionalOnLoadCode(
                    fn(string $id): string => <<<JS
                        const hidden_input = document.getElementById('$id');
                        document.addEventListener('change', (e) => {
                            if (e.target.matches('input[name="learnplace"]')) {
                                hidden_input.value = e.target.value
                            }
                        })
                        JS
                )
            ],
            $this->dic->ctrl()->getLinkTargetByClass(
                ilLearnplacesMapTourGUI::class,
                ilLearnplacesMapTourGUI::TOUR_ADD_ITEM
            ),
        )->withCancelButtonLabel($this->dic->language()->txt('close'));

        $button = $this->factory->button()->standard($this->plugin->txt('add_learnplace'), '#')->withonClick(
            $modal->getShowSignal()
        );

        return [
            'button' => $button,
            'modal' => $modal,
        ];
    }

    /**
     * @return Table
     * @throws ilCtrlException
     */
    public function getTable(): Table
    {
        $this->dic->ui()->mainTemplate()->addInlineCss('.c-table-data__positioninput label { display: none; }');

        // Sammelaktionen -> löschen
        $url = $this->dic->ctrl()->getLinkTargetByClass(
            ilLearnplacesMapTourGUI::class,
            ilLearnplacesMapTourGUI::TOUR_DELETE_ITEM
        );
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

        $url = $this->dic->ctrl()->getLinkTargetByClass(
            ilLearnplacesMapTourGUI::class,
            ilLearnplacesMapTourGUI::TOUR_SAVE_ORDER
        );
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

    /**
     * @return Tree
     */
    public function getRepositoryTree(): Tree
    {
        $context_ref_id = \ilLearnplacesMapPlugin::getContext();

        $tree_data = $this->tour_model->recurseTree($context_ref_id);

        return $this->factory->tree()->expandable("Lernorte", new RepositoryTreeRecursion($this->dic, $this->tour_model))
            ->withData([$tree_data])
            ->withEnvironment([
                'map_id' => $this->map_id,
                'tour_model' => $this->tour_model,
            ]);
    }

    /**
     * @return string
     * @throws \JsonException
     */
    public function getMap(): string
    {
        $tpl = $this->dic->ui()->mainTemplate();
        $tpl->addJavaScript('Customizing/global/plugins/Services/COPage/PageComponent/LearnplacesMap/dist/bundle.js');

        $tour_map_data = $this->tour_model->getTourMap($this->map_id);

        if (!$tour_map_data) {
            return ' ';
        }

        $learnplaces_json = json_encode(['learnplaces' => $tour_map_data['tour_learnplaces']], JSON_THROW_ON_ERROR);

        $link_list = array_map(
            fn($item) => "<li><a href='{$item['url']}' target='_self'>{$item['title']}</a></li>",
            $tour_map_data['tour_learnplaces']
        );
        $html_link_list = '<ol>' . implode('', $link_list) . '</ol>';

        $popover_html = $this->learnplacesListPopover($html_link_list);

        $content_html = <<<HTML
            <script type="application/json" data-learnplaces-tour="learnplaces-tour-$this->map_id">
            {$learnplaces_json}
            </script>
            <div class="learnplaces-tour-content">
                <div class="left-column">
                    <p>{$tour_map_data['description']}</p>
                    <div class="learnplaces-tour-links">
                        $popover_html
                    </div>
                </div>
                <div class="right-colums">
                    <div class="learnplaces-tour-map">
                        <div id="map-$this->map_id" style="width:100%; height:300px"></div>
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

    /**
     * @param string $content
     * @return string
     */
    private function learnplacesListPopover(string $content): string
    {
        $card = $this->factory->card()->standard('')->withSections(array($this->factory->legacy($content)));
        $popover = $this->factory->popover()->standard($card)->withTitle($this->plugin->txt('learnplaces'));
        $button = $this->factory->button()->standard($this->plugin->txt('show_learnplaces_button'), '#')
            ->withOnClick($popover->getShowSignal());

        return $this->dic->ui()->renderer()->render([$popover, $button]);
    }
}
