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
      console.log('selectAllLinks');
      var $checkboxes = $('.check').find('input[type=checkbox]');
      $checkboxes.prop('checked', $(this).is(':checked'));
    });

    $('#deleteSelectedLinks').click(function(){
      var selecteditems = [];
      $(".check").find("input:checked").each(function (i, ob) {
        selecteditems.push($(ob).val());
      });
      require(['TYPO3/CMS/Core/Ajax/AjaxRequest'], function (AjaxRequest) {
        // Generate a random number between 1 and 32
        new AjaxRequest(TYPO3.settings.ajaxUrls.delete_excluded_links)
          .withQueryArguments({input: selecteditems})
          .get()
          .then(async function (response) {
            const resolved = await response.resolve();
          });
      });

    })
  })
  var ManageExclusions = {};

  /**
   *
   * @param {String} prefix
   */
  ManageExclusions.toggleActionButton = function(prefix) {
    var buttonDisable = true;
    $('.' + prefix).each(function() {
      if ($(this).prop('checked')) {
        buttonDisable = false;
      }
    });

    if (prefix === 'check') {
      $('#updateLinkList').prop('disabled', buttonDisable);
    } else {
      $('#refreshLinkList').prop('disabled', buttonDisable);
    }
  };

  /**
   * Registers listeners
   */
  ManageExclusions.initializeEvents = function() {
    $('.refresh').on('click', function() {
      ManageExclusions.toggleActionButton('refresh');
    });

    $('.check').on('click', function() {
      ManageExclusions.toggleActionButton('check');
    });

    $('#ManageExclusions-list-select-pagedepth').on('change', function() {
      $('#refreshLinkList').click();
    });

    $('.t3js-update-button').on('click', function() {
      var $element = $(this);
      var name = $element.attr('name');
      var message = 'Event triggered';
      if (name === 'refreshLinkList' || name === 'updateLinkList') {
        message = $element.data('notification-message');
      }
      top.TYPO3.Notification.success(message);
    });
  };

  $(ManageExclusions.initializeEvents);


  return ManageExclusions;
});

