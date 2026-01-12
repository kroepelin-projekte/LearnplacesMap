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
    public function __construct(
        protected Container $dic,
        protected int $map_id,
    ) {
        (new CollectionModel($this->dic))->cleanupDeletedTagsFromCollectionMap($this->map_id);
    }

    public function getMap(): Legacy
    {
        $tpl = $this->dic->ui()->mainTemplate();
        $tpl->addJavaScript('Customizing/global/plugins/Services/COPage/PageComponent/LearnplacesMap/dist/bundle.js');
        $collection_model = new CollectionModel($this->dic);

        $learnplaces_list = [];
        $learnplaces = $collection_model->getLearnplacesOfCollection($this->map_id);
        foreach ($learnplaces as $learnplace_item) {
            $learnplace_ref_id = $learnplace_item['ref_id'];
            $learnplace = $learnplace_item['object'];
            /** @var Location $location */
            $location = $learnplace->getLocation();

            // Get visited status of current user
            $tour_model = new TourModel($this->dic);
            $is_visited = $tour_model->isVidited($this->dic->user()->getId(), $learnplace->getId());

            $learnplaces_list[] = [
                'title' => ilObject::_lookupTitle($learnplace->getObjectId()),
                'latitude' => $location->getLatitude(),
                'longitude' => $location->getLongitude(),
                'radius' => $location->getRadius(),
                'visited' => $is_visited ? 'true' : 'false',
                'url' => ILIAS_HTTP_PATH . '/go/xsrl/' . $learnplace_ref_id,
                'color' => $learnplace_item['color'],
                'render_index' => $learnplace_item['render_index'],
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
}