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
use ILIAS\FileUpload\MimeType;
use ILIAS\UI\Component\Legacy\Legacy;
use KPG\Learnplaces\persistence\dto\Location;
use Kpg\Plugins\LearnplacesMap\PageEditor\Tour\TourModel;

class CollectionView
{
    private CollectionModel $collection_model;
    private \ilPlugin|\ilLearnplacesMapPlugin $plugin;

    public function __construct(
        protected Container $dic,
        protected Factory $factory,
        protected int $map_id,
    ) {
        $this->collection_model = new CollectionModel($this->dic);
        $this->collection_model->cleanupDeletedTagsFromCollectionMap();
        $this->plugin = \ilObjectPlugin::getPluginObjectByType('lmap');
    }

    /**
     * @return Table
     * @throws \ilCtrlException
     */
    public function getTable(): Table
    {
        // edit single action
        $url = $this->dic->ctrl()->getLinkTargetByClass(ilLearnplacesMapCollectionGUI::class, ilLearnplacesMapCollectionGUI::COLLECTION_EDIT_GROUP_MODAL);
        $url_builder = new URLBuilder(new URI(ILIAS_HTTP_PATH . '/' . $url));
        list($url_builder, $action_parameter_token, $row_id_token) = $url_builder->acquireParameters(
            ['collection'],
            'learnplaces_map',
            'tag_name',
        );
        $actions['edit'] = $this->factory->table()->action()->single(
            $this->dic->language()->txt('edit'),
            $url_builder,
            $row_id_token,
        )->withAsync(true);

        return $this->factory->table()->data(
            '',
            [
                'tag_name' => $this->factory->table()->column()->text($this->plugin->txt('tag_name'))->withIsSortable(false),
                'active' => $this->factory->table()->column()->boolean(
                    'Aktiv', $this->factory->symbol()->glyph()->apply(),
                    '',
                )->withIsSortable(false),
                'color' => $this->factory->table()->column()->text($this->plugin->txt('color'))->withIsSortable(false),
            ],
            new TableDataRetrieval($this->dic, $this->map_id),
        )
            ->withActions($actions)
            ->withRequest($this->dic->http()->request());
    }

    /**
     * @return Modal
     * @throws \ilCtrlException
     */
    public function getEditModal(): Modal
    {
        $query = $this->dic->http()->wrapper()->query();

        $tag_name = '';
        if ($query->has('collection_tag_name')) {
            $tag_name = current($query->retrieve('collection_tag_name', $this->dic->refinery()->kindlyTo()->listOf($this->dic->refinery()->kindlyTo()->string())));
        }

        $collection_model = new CollectionModel($this->dic);
        $record = $collection_model->getGroup($tag_name, $this->map_id);
        $type = 'color_radio';
        $active = (bool) $record['active'];
        $color = $record['color'];

        return $this->factory->modal()->roundtrip(
            'Edit Group',
            null,
            [
                'tag_name' => $this->factory->input()->field()->hidden()->withValue($tag_name),
                'active' => $this->factory->input()->field()->checkbox('Aktiv')->withValue($active),
                'color_input' => $this->factory->input()->field()->colorPicker('')->withValue($color)
            ],
            $this->dic->ctrl()->getFormActionByClass(\ilLearnplacesMapCollectionGUI::class, \ilLearnplacesMapCollectionGUI::COLLECTION_SAVE_GROUP, '', true),
        )->withCancelButtonLabel($this->dic->language()->txt('close'));
    }

    /**
     * @return string
     */
    public function getMap(): string
    {
        $tpl = $this->dic->ui()->mainTemplate();
        $tpl->addJavaScript('Customizing/global/plugins/Services/COPage/PageComponent/LearnplacesMap/dist/bundle.js');
        $collection_model = new CollectionModel($this->dic);
        $tour_model = new TourModel($this->dic);

        $collection_data = $collection_model->getCollection($this->map_id);
        if ($collection_data === null) {
            return ' ';
        }

        $learnplaces_list = $collection_data['collection_learnplaces'];

        $list_html = $this->groupedLearnplacesHtml($learnplaces_list);

        $learnplaces_json = json_encode(['learnplaces' => $learnplaces_list], JSON_THROW_ON_ERROR);

        $link_list = array_map(fn($item) => "<li><a href='{$item['url']}'>{$item['title']}</a></li>", $learnplaces_list);
        $html_link_list = implode('', $link_list);

        $content_html = <<<HTML
            <script type="application/json" data-learnplaces-collection="learnplaces-collection-$this->map_id">
            {$learnplaces_json}
            </script>
            <div class="learnplaces-collection-content">
                <div class="left-column">
                    <p>{$collection_data['description']}</p>
                    <div class="learnplaces-collection-links">
                        <h3>{$this->plugin->txt('learnplace')}</h3>
                        $list_html
                    </div>
                </div>
                <div class="right-colums">
                    <div class="learnplaces-collection-map">
                        <div id="map-$this->map_id" style="width:100%; height:300px"></div>
                    </div>
                </div>
            </div>
            HTML;

        return $this->dic->ui()->renderer()->render(
            $this->dic->ui()->factory()->panel()->standard(
                $collection_data['title'],
                $this->dic->ui()->factory()->legacy($content_html)
            )
        );
    }

    /**
     * @param $learnplaces_list
     * @return string
     */
    private function groupedLearnplacesHtml($learnplaces_list): string
    {
        $grouped_learnplaces = [];
        foreach ($learnplaces_list as $learnplace) {
            if (!isset($grouped_learnplaces[$learnplace['tag_name']][$learnplace['id']])) {
                $grouped_learnplaces[$learnplace['tag_name']]['color'] = $learnplace['color'];
                $grouped_learnplaces[$learnplace['tag_name']]['learnplaces'][$learnplace['id']] = $learnplace;
            }
        }

        $html = '<div class="learnplaces-grouped-list">';

        foreach ($grouped_learnplaces as $tag_name => $group) {
            $html .= '<div class="tag-group">';
            $circle = '<span style="display: inline-block; width: 20px; height: 20px; border-radius: 50%; background-color: ' . $group['color'] . '; margin-right: 10px; vertical-align: middle;"></span>';
            $html .= '<h3 style="display: flex; align-items: center;">' . $circle . htmlspecialchars($tag_name) . '</h3>';
            $html .= '<ul>';

            foreach ($group['learnplaces'] ?? [] as $learnplace) {
                $html .= '<li>';
                $html .= '<a href="' . $learnplace['url'] . '" target="_self">' . htmlspecialchars($learnplace['title']) . '</a>';
                $html .= '</li>';
            }

            $html .= '</ul>';
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }
}