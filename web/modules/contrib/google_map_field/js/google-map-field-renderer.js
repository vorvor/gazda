/**
 * @file
 * JavaScript Google Map Field renderer.
 *
 * Renders a Google maps field in front end pages.
 */

var google_map_field_map;

(function ($, Drupal, once) {

  Drupal.behaviors.google_map_field_renderer = {
    attach: function (context) {

      $(once('.google-map-field-processed', '.google-map-field .map-container')).each(async function (index, item) {
        // Get the settings for the map from the data attributes of
        // the map container object.
        var lat = $(this).data('lat');
        var lon = $(this).data('lon');
        var zoom = parseInt($(this).data('zoom'));
        var type = $(this).data('type');
        var show_marker = $(this).data('marker-show');
        var traffic = $(this).data('traffic');
        var marker_icon = $(this).data('marker-icon');
        var show_controls = $(this).data('controls-show');
        var info_window = $(this).data('infowindow');

        lat = parseFloat(lat);
        lon = parseFloat(lon);

        // Create the map coords and map options.
        var latlng = new google.maps.LatLng(lat, lon);
        var mapOptions = {
          zoom: zoom,
          center: latlng,
          streetViewControl: false,
          mapTypeId: type,
          disableDefaultUI: show_controls ? false : true,
          mapId: "DEMO_MAP_ID",
        };

        var google_map_field_map = new google.maps.Map(this, mapOptions);

        if (traffic) {
          var trafficLayer = new google.maps.TrafficLayer();
          trafficLayer.setMap(google_map_field_map);
        }

        window.addEventListener("resize", function () {
          var center = google_map_field_map.getCenter();
          google.maps.event.trigger(google_map_field_map, "resize");
          google_map_field_map.setCenter(center);
        });

        try {
          // Drop a marker at the specified position.
          const library = await window.google.maps.importLibrary("marker");
          const { AdvancedMarkerElement } = library;

          var marker = new AdvancedMarkerElement({
            position: latlng,
            map: google_map_field_map,
          });
          if (info_window) {
            var info_markup = $(this).parent().find(".map-infowindow").html();
            var infowindow = new google.maps.InfoWindow({
              content: info_markup,
            });

            marker.addListener("click", function () {
              infowindow.open(google_map_field_map, marker);
            });
          }
        } catch (error) {
          if (console) console.error("Error importing marker library:", error);
        }

      });

    },
  };
})(jQuery, Drupal, once);
