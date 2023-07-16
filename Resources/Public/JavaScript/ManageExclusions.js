/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

/**
 * Module: TYPO3/CMS/Brofix/ManageExclusions
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

