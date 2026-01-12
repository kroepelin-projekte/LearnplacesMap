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
import CircleStyle from 'ol/style/Circle.js';
import Fill from 'ol/style/Fill.js';
import Stroke from 'ol/style/Stroke.js';
import Text from 'ol/style/Text.js';
import {buffer as bufferExtent, isEmpty as isEmptyExtent, extend as extendExtent} from 'ol/extent.js';
import {defaults as defaultControls} from 'ol/control.js';

document.addEventListener('DOMContentLoaded', initLearnplacesTourMaps);
document.addEventListener('DOMContentLoaded', initLearnplacesCollectionMaps);

function initLearnplacesTourMaps() {
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

    const markerStyleCache = new Map();
    const getMarkerStyle = (num, visited) => {
      const key = `${num}-${visited ? 'v' : 'nv'}`;
      if (markerStyleCache.has(key)) return markerStyleCache.get(key);

      // Farben: unbesucht = blau, besucht = grün
      const fillColor = /*visited ? '#2e7d32' : */'#34499a';
      const strokeColor = fillColor;

      const baseStyle = new Style({
        image: new CircleStyle({
          radius: 20,
          fill: new Fill({ color: fillColor }),
          stroke: new Stroke({ color: strokeColor, width: 2 }),
        }),
        text: new Text({
          text: String(num),
          font: 'bold 16px system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif',
          fill: new Fill({ color: '#ffffff' }),
          stroke: new Stroke({ color: strokeColor, width: 0 }),
          offsetY: 0,
        }),
      });

      // Zusatz‑Style mit Häkchen, nur wenn besucht
      const styles = [baseStyle];
      if (visited) {
        styles.push(
          new Style({
            text: new Text({
              text: '✓',
              font: 'bold 40px system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif',
              fill: new Fill({ color: '#2e7d32' }),
              stroke: new Stroke({ color: strokeColor, width: 0 }),
              offsetY: -35,
              offsetX: 0,
            }),
          })
        );
      }

      markerStyleCache.set(key, styles);
      return styles;
    };

    // Radius-Style: halbtransparenter Kreis
    const radiusStyle = new Style({
      fill: new Fill({ color: 'rgba(52,73,154,0.4)' }),
      stroke: new Stroke({ color: '#34499a', width: 3 }),
    });

    items.forEach((it, idx) => {
      if (typeof it.longitude !== 'number' || typeof it.latitude !== 'number') return;
      const lon = it.longitude;
      const lat = it.latitude;
      const center3857 = fromLonLat([lon, lat]);

      const num = idx + 1; // 1..N
      const visited = String(it.visited).toLowerCase() === 'true';
      const url = it.url;

      const pointFeature = new Feature({
        geometry: new Point(center3857),
        name: it.title || '',
        number: num,
        visited: visited,
        raw: it,
      });
      pointFeature.setStyle((feature) =>
        getMarkerStyle(feature.get('number'), feature.get('visited'))
      );
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
      controls: defaultControls({ rotate: false }),
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

    // Click and navigate to learnplace (pro Map-Instanz registrieren)
    map.on('click', (event) => {
      const feature = map.forEachFeatureAtPixel(event.pixel, (f) => f);

      if (feature) {
        const rawData = feature.get('raw');

        if (rawData && rawData.url) {
          window.open(rawData.url, '_blank');
        }
      }
    });
  });
}

function initLearnplacesCollectionMaps() {
  const scripts = document.querySelectorAll(
    'script[type="application/json"][data-learnplaces-collection^="learnplaces-collection-"]'
  );

  scripts.forEach((script) => {
    const tourAttr = script.dataset.learnplacesCollection || '';
    const mapId = tourAttr.replace(/^learnplaces-collection-/, '');
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

    const markerStyleCache = new Map();
    const getMarkerStyle = (visited, color, renderIndex) => {
      const calcIndex = Math.max(0, (renderIndex || 1) - 1);
      const index = renderIndex || 0;
      const key = `${visited ? 'v' : 'nv'}-${color}-${index}`;
      if (markerStyleCache.has(key)) return markerStyleCache.get(key);

      const fillColor = color || '#34499a';
      const strokeColor = '#ffffff';

      // Start bei 28px Radius für Index 1 (calcIndex 0)
      // Index 1 (calcIndex 0) -> 28px
      // Index 2 (calcIndex 1) -> 24px
      // Index 3 (calcIndex 2) -> 20px
      // Index 4 (calcIndex 3) -> 16px
      // Index 5 (calcIndex 4) -> 12px
      const radius = Math.max(6, 28 - (calcIndex * 4));

      const baseStyle = new Style({
        image: new CircleStyle({
          radius: radius,
          fill: new Fill({ color: fillColor }),
        }),
        // Der zIndex sorgt dafür, dass Features mit höherem Index (kleinerer Kreis)
        // über denen mit niedrigem Index gezeichnet werden.
        zIndex: index
      });

      const styles = [baseStyle];
      if (visited) {
        styles.push(
          new Style({
            text: new Text({
              text: '✓',
              font: 'bold 20px system-ui, Arial, sans-serif',
              fill: new Fill({ color: '#ffffff' }),
              offsetY: 0,
            }),
            zIndex: index + 1 // Häkchen immer über dem eigenen Kreis
          })
        );
      }

      markerStyleCache.set(key, styles);
      return styles;
    };

    const radiusStyle = new Style({
      fill: new Fill({ color: 'rgba(133,133,133,0.4)' }),
      stroke: new Stroke({ color: '#636363', width: 2 }),
    });

    // Wir sortieren die Items absteigend nach render_index (z.B. 2, 1, 0),
    // damit die größten Kreise (Index 0) zuletzt gezeichnet werden ODER
    // wir verlassen uns auf den zIndex im Style (sicherer).
    items.forEach((it) => {
      if (typeof it.longitude !== 'number' || typeof it.latitude !== 'number') return;

      const center3857 = fromLonLat([it.longitude, it.latitude]);
      const visited = String(it.visited).toLowerCase() === 'true';

      // 1. Punkt/Marker Feature
      const pointFeature = new Feature({
        geometry: new Point(center3857),
        name: it.title || '',
        visited: visited,
        color: it.color,
        render_index: parseInt(it.render_index || 0),
        raw: it,
      });

      pointFeature.setStyle((f) =>
        getMarkerStyle(f.get('visited'), f.get('color'), f.get('render_index'))
      );
      pointSource.addFeature(pointFeature);

      // 2. Optionaler Radius (nur wenn > 0)
      const radius = Number(it.radius);
      if (Number.isFinite(radius) && radius > 0) {
        const circleFeature = new Feature({
          geometry: new CircleGeom(center3857, radius),
          raw: it,
        });
        circleFeature.setStyle(radiusStyle);
        radiusSource.addFeature(circleFeature);
      }
    });

    const vectorLayerPoints = new VectorLayer({
      source: pointSource,
      // zIndex für den gesamten Layer, damit Punkte über Radien liegen
    });
    const vectorLayerRadius = new VectorLayer({ source: radiusSource });

    const map = new OLMap({
      target: containerEl,
      layers: [
        new TileLayer({ source: new XYZ({ url: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png' }) }),
        vectorLayerRadius,
        vectorLayerPoints,
      ],
      view: new View({ center: fromLonLat([0, 0]), zoom: 10 }),
      controls: defaultControls({ rotate: false }),
    });

    // Fit items
    let extent = pointSource.getExtent();
    if (!isEmptyExtent(radiusSource.getExtent())) {
      extent = extendExtent(extent, radiusSource.getExtent());
    }
    if (isEmptyExtent(extent)) return;

    if (extent[0] === extent[2] && extent[1] === extent[3]) {
      extent = bufferExtent(extent, 500);
    }
    map.getView().fit(extent, { padding: [20, 20, 20, 20], maxZoom: 16, duration: 0 });

    map.on('click', (event) => {
      const feature = map.forEachFeatureAtPixel(event.pixel, (f) => f);
      if (feature) {
        const rawData = feature.get('raw');
        if (rawData && rawData.url) {
          window.open(rawData.url, '_blank');
        }
      }
    });
  });
}
