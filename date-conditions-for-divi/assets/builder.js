/**
 * Date Conditions for Divi: Visual Builder settings.
 *
 * Plain JS, no build step. Runs in the builder app window after divi-vendor-wp-hooks.
 *
 * - Registers the dcfdDateRules / dcfdEmptyState attributes on Loop-capable elements.
 * - Draws the settings inside Divi's own Loop group (divi.module.options.loop.group.fields),
 *   shown only while Loop is on with a post-types or current-page query.
 * - Sends the rules with the builder's Loop preview request.
 * - Registers a small field component that checks the field name against ACF.
 */
( function () {
  'use strict';

  var hooks = window.vendor && window.vendor.wp && window.vendor.wp.hooks;
  if ( ! hooks ) {
    return;
  }

  // React loads after this script, so look it up at render time.
  function react() {
    return window.vendor.React;
  }

  var NS = 'dcfd';
  var DATA = window.DcfdBuilderData || {};
  var QUERY_TYPES = [ 'post_types', 'current_page' ];
  var DEFAULT_OPERATOR = 'after'; // Must match DCFD\DEFAULT_OPERATOR in includes/rules.php.
  var STATUS_COMPONENT = 'dcfd/field-status';

  var OPERATORS = {
    after: { label: 'Now is after the date' },
    before: { label: 'Now is before the date' },
    equals: { label: 'Now is equal to the date (to the second)' },
  };

  var FEATURES = { hover: false, sticky: false, responsive: false, dynamicContent: false, preset: 'content' };

  function hasLoop( metadata ) {
    return !! ( metadata && metadata.attributes && metadata.attributes.module &&
      metadata.attributes.module.settings && metadata.attributes.module.settings.advanced &&
      metadata.attributes.module.settings.advanced.loop );
  }

  function apiGet( path ) {
    return window.fetch( ( DATA.restUrl || '' ) + path, {
      credentials: 'same-origin',
      headers: { 'X-WP-Nonce': DATA.nonce || '' },
    } ).then( function ( response ) {
      if ( ! response.ok ) {
        throw new Error( 'HTTP ' + response.status );
      }
      return response.json();
    } );
  }

  /* ---------------------------------------------------------------------------------------
   * Attributes. Per instance: not responsive, hover or sticky, and not part of presets.
   * ------------------------------------------------------------------------------------- */
  hooks.addFilter( 'divi.moduleLibrary.moduleAttributes', NS, function ( attributes, metadata ) {
    if ( ! hasLoop( metadata ) ) {
      return attributes;
    }
    attributes.dcfdDateRules = { type: 'object' };
    attributes.dcfdEmptyState = { type: 'object' };
    return attributes;
  } );

  /* ---------------------------------------------------------------------------------------
   * Library items for the empty-state select, loaded once.
   * ------------------------------------------------------------------------------------- */
  var layoutOptions = { '': { label: 'None (show Divi\'s "No Results Found")' } };
  apiGet( 'layouts' ).then( function ( items ) {
    ( items || [] ).forEach( function ( item ) {
      layoutOptions[ String( item.id ) ] = {
        label: item.title + ( item.type ? ' (' + item.type + ')' : '' ),
      };
    } );
  } ).catch( function () {} );

  /* ---------------------------------------------------------------------------------------
   * Settings inside the Loop group.
   * ------------------------------------------------------------------------------------- */
  hooks.addFilter( 'divi.module.options.loop.group.fields', NS, function ( fields, context ) {
    var loop = ( context && context.loopValues ) || {};
    var queryType = loop.queryType || 'post_types';
    var visible = false !== ( context && context.visible ) && 'on' === loop.enable && QUERY_TYPES.indexOf( queryType ) !== -1;
    var postTypes = ( Array.isArray( loop.subTypes ) ? loop.subTypes : [] )
      .map( function ( item ) { return item && item.value; } )
      .filter( Boolean );

    // Use the Loop group's own group name so our fields render like Divi's.
    var groupName;
    Object.keys( fields || {} ).some( function ( key ) {
      groupName = fields[ key ] && fields[ key ].groupName;
      return !! groupName;
    } );

    function field( attrName, subName, label, description, component, extra ) {
      return Object.assign( {
        attrName: attrName,
        subName: subName,
        label: label,
        description: description,
        features: FEATURES,
        render: true,
        groupName: groupName,
        visible: visible,
        component: component,
      }, extra || {} );
    }

    function ruleFields( n, description ) {
      var sub = 'rule' + n;
      var out = {};
      out[ 'dcfdRule' + n + 'Field' ] = field(
        'dcfdDateRules.innerContent', sub + 'Field',
        'Date rule ' + n + ': ACF field name',
        description,
        { type: 'field', name: 'divi/text' }
      );
      out[ 'dcfdRule' + n + 'Status' ] = field(
        'dcfdDateRules.innerContent', sub + 'Field', '', '',
        { type: 'field', name: STATUS_COMPONENT, props: { postTypes: postTypes, queryType: queryType } }
      );
      out[ 'dcfdRule' + n + 'Operator' ] = field(
        'dcfdDateRules.innerContent', sub + 'Operator',
        'Date rule ' + n + ': show a post when',
        'Compared with the current date and time in the site timezone. A Date Picker date counts as 23:59:59 on that day.',
        { type: 'field', name: 'divi/select', props: { options: OPERATORS } },
        { defaultAttr: { desktop: { value: ( function () { var v = {}; v[ sub + 'Operator' ] = DEFAULT_OPERATOR; return v; } )() } } }
      );
      return out;
    }

    return Object.assign(
      {},
      fields,
      ruleFields( 1, 'An ACF Date Picker or Date Time Picker field, e.g. event_ends. For a field inside an ACF Group, use group_field, e.g. key_facts_event_ends. Posts with no value for the field are always shown.' ),
      ruleFields( 2, 'Optional. Both rules must pass for a post to show.' ),
      {
        dcfdEmptyState: field(
          'dcfdEmptyState.innerContent', undefined,
          'When no posts match, show',
          'A Divi Library item that replaces this whole element when the Loop is empty. Match the item to this element (a section item for a section, and so on). Not available on child modules such as accordion items, slides or tabs.',
          { type: 'field', name: 'divi/select', props: { options: layoutOptions } }
        ),
      }
    );
  } );

  /* ---------------------------------------------------------------------------------------
   * Builder preview: send the rules with the Loop query request (post-types loops only;
   * the current-page preview stays unfiltered by decision).
   * ------------------------------------------------------------------------------------- */
  hooks.addFilter( 'divi.module.layout.childModule.loop.resultsQueryParams', NS, function ( queryParams, attrs, moduleId, queryType ) {
    var value = attrs && attrs.dcfdDateRules && attrs.dcfdDateRules.innerContent &&
      attrs.dcfdDateRules.innerContent.desktop && attrs.dcfdDateRules.innerContent.desktop.value;

    if ( 'post_types' === queryType && value && ( value.rule1Field || value.rule2Field ) ) {
      queryParams.set( 'dcfd_rules', JSON.stringify( value ) );
    } else {
      queryParams.delete( 'dcfd_rules' );
    }
    return queryParams;
  } );

  /* ---------------------------------------------------------------------------------------
   * Field status message (SPEC 5.4). Warnings never block saving.
   * ------------------------------------------------------------------------------------- */
  var statusCache = {};

  function messageFor( result, queryType ) {
    if ( 'current_page' === queryType ) {
      return { tone: 'info', text: 'Can\'t verify for this query type, will be checked when the page loads' };
    }
    switch ( result.status ) {
      case 'found':
        return { tone: 'ok', text: 'Date field found (' + ( 'date_picker' === result.type ? 'Date Picker' : 'Date Time Picker' ) + ')' };
      case 'not_found':
        return { tone: 'warn', text: 'Field not found for ' + ( result.queried || [] ).join( ', ' ) };
      case 'not_date':
        return { tone: 'warn', text: 'Field is not a date field' };
      case 'conflict':
        return { tone: 'warn', text: 'Field is a different type on different post types, so this rule will be ignored' };
      case 'invalid_name':
        return { tone: 'warn', text: 'Use only lowercase letters, numbers, _ and -' };
      case 'no_acf':
        return { tone: 'warn', text: 'ACF is not active, so date rules are ignored' };
      default:
        return null;
    }
  }

  function FieldStatus( props ) {
    var React = react();
    var h = React.createElement;
    var name = String( props.value || '' ).trim();
    var postTypes = props.postTypes || [];
    var queryType = props.queryType || 'post_types';
    var key = name + '|' + postTypes.join( ',' );
    var state = React.useState( statusCache[ key ] || null );
    var result = state[ 0 ];
    var setResult = state[ 1 ];

    React.useEffect( function () {
      if ( ! name || 'current_page' === queryType ) {
        return undefined;
      }
      if ( statusCache[ key ] ) {
        setResult( statusCache[ key ] );
        return undefined;
      }
      setResult( null );
      var cancelled = false;
      var timer = window.setTimeout( function () {
        apiGet( 'field?name=' + encodeURIComponent( name ) + '&post_types=' + encodeURIComponent( postTypes.join( ',' ) ) )
          .then( function ( response ) {
            statusCache[ key ] = response;
            if ( ! cancelled ) {
              setResult( response );
            }
          } )
          .catch( function () {} );
      }, 400 );
      return function () {
        cancelled = true;
        window.clearTimeout( timer );
      };
    }, [ key, queryType ] );

    if ( ! name ) {
      return null;
    }

    var message = 'current_page' === queryType ? messageFor( {}, queryType ) : ( result ? messageFor( result, queryType ) : null );
    if ( ! message ) {
      return h( 'div', { className: 'dcfd-field-status', style: { fontSize: '12px', opacity: 0.7 } }, 'Checking field…' );
    }

    var colours = { ok: '#1d7f4f', warn: '#b45309', info: '#475569' };
    return h( 'div', {
      className: 'dcfd-field-status dcfd-field-status--' + message.tone,
      role: 'status',
      style: { fontSize: '12px', color: colours[ message.tone ], marginTop: '-4px' },
    }, message.text );
  }

  hooks.addFilter( 'divi.fieldLibrary.getFieldComponent', NS, function ( component, name ) {
    return STATUS_COMPONENT === name ? FieldStatus : component;
  } );
  hooks.addFilter( 'divi.fieldLibrary.fieldComponentMap', NS, function ( map ) {
    map[ STATUS_COMPONENT ] = { name: STATUS_COMPONENT, component: FieldStatus };
    return map;
  } );

  if ( ! DATA.acfActive && window.console ) {
    window.console.warn( 'Date Conditions for Divi: ACF is not active, so date rules are ignored.' );
  }
}() );
