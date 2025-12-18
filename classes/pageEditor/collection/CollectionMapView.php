<?php

declare(strict_types=1);

namespace Kpg\Plugins\LearnplacesMap\PageEditor\Collection;

use ILIAS\DI\Container;
use ILIAS\UI\Component\Legacy\Legacy;
use ilException;
use KPG\Learnplaces\persistence\dto\Learnplace;
use KPG\Learnplaces\persistence\repository\LearnplaceRepository;
use KPG\Learnplaces\container\PluginContainer;
use ilObject;
use KPG\Learnplaces\persistence\repository\LearnplaceRepositoryImpl;
use KPG\Learnplaces\persistence\dto\Location;
use KPG\Learnplaces\persistence\dto\Configuration;
use Kpg\Plugins\LearnplacesMap\PageEditor\Tour\TourModel;

class CollectionMapView
{
    private LearnplaceRepository $learnplace_service;

    public function __construct(
        protected Container $dic,
        protected int $map_id,
    ) {
        /** @var LearnplaceRepository $learnplace_service  */
        $this->learnplace_service = PluginContainer::resolve(LearnplaceRepository::class);
    }

    public function getMap(): Legacy
    {
        $tpl = $this->dic->ui()->mainTemplate();
        $tpl->addJavaScript('Customizing/global/plugins/Services/COPage/PageComponent/LearnplacesMap/dist/bundle.js');

        $learnplaces_list = [];
        $learnplaces = $this->getLearnplacesOfCollection();
        foreach ($learnplaces as $learnplace_item) {
            $learnplace_ref_id = $learnplace_item['ref_id'];
            $learnplace = $learnplace_item['object'];
            /** @var Location $location */
            $location = $learnplace->getLocation();

            // Get visited status of current user
            $tour_model = new TourModel($this->dic, $this->dic->ui()->factory());
            $is_visited = $tour_model->isVidited($this->dic->user()->getId(), $learnplace->getId());

            $learnplaces_list[] = [
                'title' => ilObject::_lookupTitle($learnplace->getObjectId()),
                'latitude' => $location->getLatitude(),
                'longitude' => $location->getLongitude(),
                'radius' => $location->getRadius(),
                'visited' => $is_visited ? 'true' : 'false',
                'url' => ILIAS_HTTP_PATH . '/go/xsrl/' . $learnplace_ref_id,
                'color' => $learnplace_item['color'],
            ];
        }

        $learnplaces_json = json_encode(['learnplaces' => $learnplaces_list], JSON_THROW_ON_ERROR);

        return $this->dic->ui()->factory()->legacy(
            <<<HTML
            <script type="application/json" data-learnplaces-collection="learnplaces-collection-{$this->map_id}">
            {$learnplaces_json}
            </script>
            <div id="map-{$this->map_id}" style="width:100%; height:600px"></div>
            HTML
        );
    }

    private function getLearnplacesOfCollection(): \Generator
    {
        $db = $this->dic->database();

        $sql = $db->queryF(
            'SELECT context_ref_id, tag_name, active, color, resource_id FROM kpg_lmap_collection JOIN kpg_lmap_map ON kpg_lmap_map.id = kpg_lmap_collection.map_id WHERE map_id = %s',
            [
                'integer',
            ],
            [
                $this->map_id,
            ],
        );

        $rendered_learnplaces = [];
        while ($row = $db->fetchAssoc($sql)) {
            if (!$row['active']) {
                continue;
            }
            $context_ref_id = (int) $row['context_ref_id'];
            $tag_name = $row['tag_name'];
            $color = $row['color'];
            $resource_id = $row['resource_id'];

            $learnplaces = $this->dic->repositoryTree()->getSubTree($this->dic->repositoryTree()->getNodeData($context_ref_id), false, ['xsrl']);

            foreach ($learnplaces as $learnplace_ref_id) {
                $obj_id = ilObject::_lookupObjectId($learnplace_ref_id);

                if (!ilObject::_exists($obj_id)) {
                    continue;
                }

                $object_learnplace = $this->learnplace_service->findByObjectId($obj_id);

                /** @var Configuration $configuration */
                $configuration = $object_learnplace->getConfiguration();

                if (!$configuration->isOnline()) {
                    continue;
                }

                $tags_string = $object_learnplace->getConfiguration()->getTags();
                $tags_array = ($tags_string !== "")
                    ? array_map('trim', explode(',', $tags_string))
                    : [];

                if (!in_array($tag_name, $tags_array)) {
                    continue;
                }

                if (in_array($learnplace_ref_id, $rendered_learnplaces)) {
                    continue;
                }

                $rendered_learnplaces[] = $learnplace_ref_id;

                yield [
                    'ref_id' => $learnplace_ref_id,
                    'object' => $object_learnplace,
                    'color' => $color,
                ];
            }
        }
    }
}