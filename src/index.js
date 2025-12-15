import OLMap from 'ol/Map.js';
import View from 'ol/View.js';
import TileLayer from 'ol/layer/Tile.js';
import VectorLayer from 'ol/layer/Vector.js';
import XYZ from 'ol/source/XYZ.js';
import VectorSource from 'ol/source/Vector.js';
import Feature from 'ol/Feature.js';
import Point from 'ol/geom/Point.js';
import CircleGeom from 'ol/geom/Circle.js';
import {fromLonLat} from 'ol/proj.js';
import Style from 'ol/style/Style.js';
import Icon from 'ol/style/Icon.js'; // optional, falls du statt Kreisen ein Icon willst
import CircleStyle from 'ol/style/Circle.js';
import Fill from 'ol/style/Fill.js';
import Stroke from 'ol/style/Stroke.js';
import Text from 'ol/style/Text.js';
import {buffer as bufferExtent, isEmpty as isEmptyExtent, extend as extendExtent} from 'ol/extent.js';

document.addEventListener('DOMContentLoaded', initLearnplacesMaps);

function initLearnplacesMaps() {
  const scripts = document.querySelectorAll(
    'script[type="application/json"][data-learnplaces-tour^="learnplaces-tour-"]'
  );

  scripts.forEach((script) => {
    const tourAttr = script.dataset.learnplacesTour || '';
    const mapId = tourAttr.replace(/^learnplaces-tour-/, '');
    const containerId = `map-${mapId}`;
    const containerEl = document.getElementById(containerId);
    if (!containerEl) {
      console.warn(`Container #${containerId} nicht gefunden – Map wird übersprungen.`);
      return;
    }

    let data;
    try {
      data = JSON.parse(script.textContent || '{}');
    } catch (e) {
      console.error('Ungültiges JSON in', script, e);
      return;
    }

    const items = Array.isArray(data.learnplaces) ? data.learnplaces : [];

    // Quellen: Punkte (Marker) und Radien (Kreise)
    const pointSource = new VectorSource();
    const radiusSource = new VectorSource();

    // Nummern-Style: Marker + Label
    const markerStyleCache = new Map(); // key: nummer -> Style
    const getMarkerStyle = (num) => {
      if (markerStyleCache.has(num)) return markerStyleCache.get(num);
      const style = new Style({
        image: new CircleStyle({
          radius: 20,
          fill: new Fill({ color: '#34499a' }),
          stroke: new Stroke({ color: '#34499a', width: 2 }),
        }),
        text: new Text({
          text: String(num),
          font: 'bold 16px system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif',
          fill: new Fill({ color: '#ffffff' }),
          stroke: new Stroke({ color: '#34499a', width: 0 }),
          offsetY: 0,
        }),
      });
      markerStyleCache.set(num, style);
      return style;
    };

    // Radius-Style: halbtransparenter Kreis
    const radiusStyle = new Style({
      fill: new Fill({ color: 'rgba(52,73,154,0.4)' }),
      stroke: new Stroke({ color: '#34499a', width: 3 }),
    });

    // Features anlegen (Reihenfolge = Nummerierung)
    items.forEach((it, idx) => {
      if (typeof it.longitude !== 'number' || typeof it.latitude !== 'number') return;
      const lon = it.longitude;
      const lat = it.latitude;
      const center3857 = fromLonLat([lon, lat]);

      const num = idx + 1; // 1..N

      // Punkt-Feature mit Style-Funktion für Nummern
      const pointFeature = new Feature({
        geometry: new Point(center3857),
        name: it.title || '',
        number: num,
        raw: it,
      });
      pointFeature.setStyle((feature) => getMarkerStyle(feature.get('number')));
      pointSource.addFeature(pointFeature);

      // Optionaler Radius (nur wenn > 0)
      const radius = Number(it.radius);
      if (Number.isFinite(radius) && radius > 0) {
        const circleFeature = new Feature({
          geometry: new CircleGeom(center3857, radius), // Radius in Metern (EPSG:3857)
          relatedNumber: num,
          raw: it,
        });
        circleFeature.setStyle(radiusStyle);
        radiusSource.addFeature(circleFeature);
      }
    });

    const vectorLayerPoints = new VectorLayer({ source: pointSource });
    const vectorLayerRadius = new VectorLayer({ source: radiusSource });

    const map = new OLMap({
      target: containerEl,
      layers: [
        new TileLayer({ source: new XYZ({ url: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png' }) }),
        vectorLayerRadius,
        vectorLayerPoints,
      ],
      view: new View({ center: fromLonLat([0, 0]), zoom: 10 }),
    });

    // Fit items
    let extent = pointSource.getExtent();
    if (!isEmptyExtent(radiusSource.getExtent())) {
      extent = extendExtent(extent, radiusSource.getExtent());
    }
    if (isEmptyExtent(extent)) {
      return;
    }
    if (extent[0] === extent[2] && extent[1] === extent[3]) {
      extent = bufferExtent(extent, 500); // 500 m Puffer
    }
    // Ohne Animation direkt auf die Zielausdehnung positionieren
    map.getView().fit(extent, { padding: [20, 20, 20, 20], maxZoom: 16, duration: 0 });
  });
}