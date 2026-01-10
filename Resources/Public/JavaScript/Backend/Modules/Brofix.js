/**
 * Module: TYPO3/CMS/Brofix/Brofix
 *
 * Is included via Configuration/JavaScriptModules.php
 */

import $ from 'jquery';

class Brofix {

  constructor()
  {
    this.addEvemtListeners();
  }

  addEvemtListeners()
  {

    // reload list on changing these values
    $('#linktype_searchFilter').on('change', function () {
      $('#refreshLinkList').click();
    })

    $('#brofix-list-select-check_status').on('change', function () {
      $('#refreshLinkList').click();
    })

    $('#url_match_searchFilter').on('change', function () {
      $('#refreshLinkList').click();
    })

    $('.view_mode').on('change', function () {
      $('#refreshLinkList').click();
    })

    // reset filter
    $('#brofix-reset-filter').on('click', function () {
      $('#uid_searchFilter').attr('value', '');
      $('#type_searchFilter').attr('value', '');
      $('#linktype_searchFilter').val('all');
      $('#url_searchFilter').attr('value', '');
      $('#error_searchFilter').attr('value', '');
      $('#url_match_searchFilter').val('partial');
      $('#brofix-list-select-check_status').val('1');
      $('#refreshLinkList').click();
    });

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
      var message = $element.data('notification-message');
      top.TYPO3.Notification.success(message);
    });

  }

  /**
   *
   * @param {String} prefix
   */
  toggleActionButton(prefix)
  {
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
  }
}

export default new Brofix;
