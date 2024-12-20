/**
 * Module: TYPO3/CMS/Brofix/ManageExclusions
 *
 * Uses requirejs, is deprecated
 */
define(['jquery'], function($) {
  'use strict';

  $(document).ready(function () {

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
        require(['TYPO3/CMS/Core/Ajax/AjaxRequest'], function (AjaxRequest) {
          new AjaxRequest(TYPO3.settings.ajaxUrls.delete_excluded_links)
            .withQueryArguments({input: selecteditems})
            .get()
            .then(async function (response) {
              const resolved = await response.resolve();
              $('#refreshLinkList').click();
              // todo: show flash message, return number of affected froms from ExcludeLinkTargetRepository
              //top.TYPO3.Notification.info('', '');
            });
        });
      } else {
        // todo: show flash message (with localized message)
      }

    })
  })
  var ManageExclusions = {};


  return ManageExclusions;
});

