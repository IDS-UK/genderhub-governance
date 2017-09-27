var App = {};

App.StickyFooter = ( function( $ ) {

  var $contentWrapper;
  var $push;
  var $footer;

  // Setup
  // -----------------------------------------------------------------------------------------------

  function init() {
    $contentWrapper = $( '.StickyFooterContentWrapper' );
    $push = $( '.StickyFooterPush' );
    $footer = $( '.PageFooter' );
    addListeners();
    resizeFooter();
    expandCourseSummary();

    $buddypressNotice = $( '#bp_fp_notice' );
    if ( $buddypressNotice[ 0 ] ) {
      $( '#buddypress' ).prepend( $buddypressNotice );
    }
  };

  function addListeners() {
    $( window ).resize( function() {
      resizeFooter();
    } );
    $( window ).resize();
  }

  function resizeFooter() {
    var footerHeight = $footer.outerHeight();
    $push.height( footerHeight + 30 );
    $contentWrapper.css( 'marginBottom', '-' + footerHeight + 'px' );
  }

  // Because Learndash loads the relevant CSS into the page body, we can't override the inital style
  // without using a trump which prevents expansion and contraction from working, so we use JS instead
  function expandCourseSummary() {
    if ( $( window ).width() > 700 ) {
      $( '#learndash_course_content #learndash_lessons .learndash_topic_dots' ).slideDown( 0 );
    }
  }

  // Module
  // -----------------------------------------------------------------------------------------------

  return {
    init: init
  };

}( jQuery ) );

App.StickyFooter.init();