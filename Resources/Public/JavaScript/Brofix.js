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
 * Module: TYPO3/CMS/Brofix/Brofix
 */

define(['jquery'], function($) {
  'use strict';
  $(document).ready(function () {
    $('#uidButton').on('click', function (){
      $('#uid_searchFilter').val('')
    })

    $('#urlButton').on('click', function (){
      $('#url_searchFilter').val('')
    })

    $('#titleButton').on('click', function (){
      $('#title_searchFilter').val('')
    })

    $('#excludeUrlButton').on('click', function (){
      $('#excludeUrl_filter').val('')
    })

    $('.selectAllLinks').click(function() {
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
  var Brofix = {};

  /**
   *
   * @param {String} prefix
   */
  Brofix.toggleActionButton = function(prefix) {
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
  Brofix.initializeEvents = function() {
    $('.refresh').on('click', function() {
      Brofix.toggleActionButton('refresh');
    });

    $('.check').on('click', function() {
      Brofix.toggleActionButton('check');
    });

    $('#brofix-list-select-pagedepth').on('change', function() {
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

  $(Brofix.initializeEvents);


  return Brofix;
});

