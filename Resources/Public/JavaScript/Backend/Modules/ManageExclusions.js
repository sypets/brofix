/**
 * Module: TYPO3/CMS/Brofix/ManageExclusions
 *
 * Is included via Configuration/JavaScriptModules.php
 */

import $ from 'jquery';
import AjaxRequest from '@typo3/core/ajax/ajax-request.js';

class ManageExclusions
{

  constructor()
  {
    this.addEvemtListeners();
  }

  addEvemtListeners()
  {

    $('#excludeUrlButton').on('click', function (){
      $('#excludeUrl_filter').val('')
    })

    $('.selectAllLinks').click(function() {
      var $checkboxes = $('.check').find('input[type=checkbox]');
      $checkboxes.prop('checked', $(this).is(':checked'));
    });

    $('#deleteSelectedLinks').click(function() {
      var selecteditems = [];

      $(".check").find("input:checked").each(function (i, ob) {
        selecteditems.push($(ob).val());
      });
      if (selecteditems.length > 0) {
          new AjaxRequest(TYPO3.settings.ajaxUrls.delete_excluded_links)
            .withQueryArguments({input: selecteditems})
            .get()
            .then(async function (response) {
              const resolved = await response.resolve();
              $('#refreshLinkList').click();
              // todo: show flash message, return number of affected froms from ExcludeLinkTargetRepository
              //top.TYPO3.Notification.info('', '');
            });
      } else {
        // todo: show flash message (with localized message)
      }

    })
  }
}

export default new ManageExclusions;

