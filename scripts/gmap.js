var map;        // Google Map object
var myLatlng;   // The center of CAS
var myOptions;  // Google Map options

// var dealers must be set by whatever page includes this one.
// -----------------------------------------------------------

// Display the dealer icons
// ------------------------
$(document).ready(function()
{
    myLatlng = new google.maps.LatLng(33.8629,-118.1003);
    myOptions = {
      zoom: 16,
      center: myLatlng,
      mapTypeId: google.maps.MapTypeId.ROADMAP
    }

    geocoder = new google.maps.Geocoder();

    initialize();
});

function initialize()
{
    map = new google.maps.Map(document.getElementById("theMap"), myOptions);

    var d;      // Dealer iterator
    var pos;    // Position of dealer
    var icon;   // Dealer icon
    var mapInfoContainer; // Sidebar div to write dealer info on marker click
    for(var i =0; i < dealers.length; ++i)
    {
        d = dealers[i];
        pos = new google.maps.LatLng(d.lat,d.lng);
        icon = '/images/'+d.key+'_b.png';
        mapInfoContainer = $('#mapInfoContainer');
        var marker = new google.maps.Marker(
        {
            map: map,
            position: pos,
            icon: icon,
            title: d.name,
            phone: d.phone,
            addr: d.addr,
            hours: d.hours,
            url: d.url
        });
        google.maps.event.addListener(marker, 'click', function() {
          var dInfo = '<h2>' + this.title + '</h2>'+"<p><a href='http://maps.google.com/maps?daddr=" + this.addr + ",Cerritos,CA,90703' target='_blank' >Get Directions</a> &#124; <a href='" + this.url + "' target='_blank'>Go to Website</a></p><p>" + this.phone + "</p><address>" + this.addr + "<br />Cerritos, CA 90703</address>" + this.hours;
          $(mapInfoContainer).fadeOut(200, function() { $(this).html(dInfo); }).fadeIn(500);
        });
        d.marker = marker;
    }
}