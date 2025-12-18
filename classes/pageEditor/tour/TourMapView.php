<?php

declare(strict_types=1);

namespace Kpg\Plugins\LearnplacesMap\PageEditor\Tour;

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

class TourMapView
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
        $learnplaces = $this->getLearnplacesOfTour();
        foreach ($learnplaces as $learnplace_item) {
            $learnplace_ref_id = $learnplace_item['ref_id'];
            $learnplace = $learnplace_item['object'];
            /** @var Location $location */
            $location = $learnplace->getLocation();

            /** @var Configuration $configuration */
            $configuration = $learnplace->getConfiguration();

            if (!$configuration->isOnline()) {
                continue;
            }

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
            ];
        }

        $learnplaces_json = json_encode(['learnplaces' => $learnplaces_list], JSON_THROW_ON_ERROR);

        return $this->dic->ui()->factory()->legacy(
            <<<HTML
            <script type="application/json" data-learnplaces-tour="learnplaces-tour-{$this->map_id}">
            {$learnplaces_json}
            </script>
            <div id="map-{$this->map_id}" style="width:100%; height:600px"></div>
            HTML
        );
    }

    private function getLearnplacesOfTour(): \Generator
    {
        $db = $this->dic->database();

        $sql = $db->queryF(
            'SELECT * FROM kpg_lmap_tour WHERE map_id = %s ORDER BY position',
            [
                'integer',
            ],
            [
                $this->map_id,
            ],
        );

        while ($row = $db->fetchAssoc($sql)) {
            $obj_id = ilObject::_lookupObjectId((int) $row['learnplace_ref_id']);

            if (!ilObject::_exists($obj_id)) {
                continue;
            }

            yield [
                'ref_id' => (int) $row['learnplace_ref_id'],
                'object' => $this->learnplace_service->findByObjectId($obj_id),
            ];
        }
    }
}