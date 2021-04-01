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
