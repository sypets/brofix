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

define(['jquery',
  'TYPO3/CMS/Backend/PageTree/PageTreeToolbar'], function($, PageTreeToolbar) {
  'use strict';
  $(document).ready(function () {

    // filter in page tree
    $('.brofix-pagetree-filter').on('click', function(event) {
        var uid = $(this).attr("data-uid");
        console.log('brofix pagetree filter event uid=' + uid);
        $('input.form-control.search-input', parent.document).
          val(uid).trigger('input');
        parent.$('input.form-control.search-input').trigger('input');
        window.parent.$(window.parent.document).trigger('input');
      });

    // reload list on changing these values
    $('#linktype_searchFilter').on('change', function () {
      $('#refreshLinkList').click();
    })

    $('#url_match_searchFilter').on('change', function () {
      $('#refreshLinkList').click();
    })

    $('#view_table_complex').on('change', function () {
      $('#refreshLinkList').click();
    })

    $('#view_table_min').on('change', function () {
      $('#refreshLinkList').click();
    })

    // clear input text fields with X button
    $('#uidButton').on('click', function () {
      $('#uid_searchFilter').val('');
      $('#refreshLinkList').click();
    })

    $('#urlButton').on('click', function (){
      $('#url_searchFilter').val('');
      $('#refreshLinkList').click();
    })

    $('#titleButton').on('click', function (){
      $('#title_searchFilter').val('');
      $('#refreshLinkList').click();
    })

    $('#excludeUrlButton').on('click', function (){
      $('#excludeUrl_filter').val('');
      $('#refreshLinkList').click();
    })

    /** move to extra JavaScript module for "Manage Exclusions" */
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

