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

class TourMap
{
    public function __construct(
        protected Container $dic,
        protected int $map_id,
    ) {
    }

    public function getMap(): Legacy
    {
        $tpl = $this->dic->ui()->mainTemplate();
        $tpl->addJavaScript('Customizing/global/plugins/Services/COPage/PageComponent/LearnplacesMap/dist/bundle.js');

        $learnplaces_list = [];
        $learnplaces = $this->getLearnplacesOfTour();
        foreach ($learnplaces as $learnplace) {
            /** @var Location $location */
            $location = $learnplace->getLocation();

            $learnplaces_list[] = [
                'title' => ilObject::_lookupTitle($learnplace->getObjectId()),
                'latitude' => $location->getLatitude(),
                'longitude' => $location->getLongitude(),
                'radius' => $location->getRadius(),
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
            yield $this->getLearnplaceObj((int) $row['learnplace_ref_id']);
        }
    }

    private function getLearnplaceObj(int $learnplace_ref_id): Learnplace
    {
        if (!\ilObjectPlugin::getPluginObjectByType('xsrl')->isActive()) {
            throw new ilException('Learnplaces plugin is not active.');
        }

        /** @var LearnplaceRepository $learnplace_service  */
        $learnplace_service = PluginContainer::resolve(LearnplaceRepository::class);
        return $learnplace_service->findByObjectId(ilObject::_lookupObjectId($learnplace_ref_id));
    }
}