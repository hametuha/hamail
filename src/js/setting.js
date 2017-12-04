/**
 * Setting helper
 */

(function ($) {

  'use strict';

  var timer = null;

  var updateCsv = function(){
    if ( timer ) {
      clearTimeout(timer);
    }
    setTimeout( function() {
      var str = $('#hamail_fields_to_sync').val().split("\n");

      $('.hamail-csv-preview tr').each( function(index, tr){
        var $tr = $(tr);
        // Clear cells
        $tr.find('td').remove();
        if(str[index]){
          $.each(str[index].split(','), function(i, cell){
            cell = $.trim(cell);
            $tr.append('<td>' + cell + '</td>');
          });
        }
      });
    }, 500 );
  };

  $(document).ready(updateCsv);

  $('#hamail_fields_to_sync').on('keyup', updateCsv);

})(jQuery);
