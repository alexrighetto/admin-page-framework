<?php
if ( ! class_exists( 'AdminPageFramework_InputField' ) ) :
/**
 * Provides methods for rendering form input fields.
 *
 * @since			2.0.0
 * @since			2.0.1			Added the <em>size</em> type.
 * @since			2.1.5			Separated the methods that defines field types to different classes.
 * @extends			AdminPageFramework_Utility
 * @package			Admin Page Framework
 * @subpackage		Admin Page Framework - Field
 */
class AdminPageFramework_InputField extends AdminPageFramework_Utility {
		
	/**
	 * Indicates whether the creating fields are for meta box or not.
	 * @since			2.1.2
	 */
	private $_bIsMetaBox = false;
			
	public function __construct( &$aField, &$aOptions, $aErrors, &$aFieldTypeDefinitions, &$oMsg ) {
			
		$aFieldTypeDefinition = isset( $aFieldTypeDefinitions[ $aField['type'] ] ) ? $aFieldTypeDefinitions[ $aField['type'] ] : $aFieldTypeDefinitions['default'];
		$this->aField = $this->uniteArrays( $aField, $aFieldTypeDefinition['aDefaultKeys'] );
		$this->aFieldTypeDefinitions = $aFieldTypeDefinitions;
		$this->aOptions = $aOptions;
		$this->aErrors = $aErrors ? $aErrors : array();
		$this->oMsg = $oMsg;
				
		// Global variable
		$GLOBALS['aAdminPageFramework']['aFieldFlags'] = isset( $GLOBALS['aAdminPageFramework']['aFieldFlags'] )
			? $GLOBALS['aAdminPageFramework']['aFieldFlags']
			: array();
		
		if ( ! isset( $GLOBALS['aAdminPageFramework']['bEnqueuedRegisterCallbackScript'] ) ) {
			
			add_action( 'admin_footer', array( $this, '_replyToAddRegisterCallbackScript' ) );
			$GLOBALS['aAdminPageFramework']['bEnqueuedRegisterCallbackScript'] = true;
			
		}
	}	
	
	/**
	 * 
	 * @since			2.0.0
	 * @since			3.0.0			Dropped the section key. Deprecated the 'name' field key to override the name attribute since the new 'attribute' key supports the functionality.
	 */
	private function _getInputFieldName( $aField=null ) {
		
		$aField = isset( $aField ) ? $aField : $this->aField;
		
		return isset( $aField['option_key'] ) // the meta box class does not use the option key
			? "{$aField['option_key']}[{$aField['page_slug']}][{$aField['field_id']}]"
			: $aField['field_id'];
		
	}
	
	/**
	 * 
	 * @since			2.0.0
	 * @since			3.0.0			Removed the check of the 'value' and 'default' keys.
	 */
	private function _getInputFieldValue( &$aField, $aOptions ) {	

		// Check if a previously saved option value exists or not.
		//  for regular setting pages. Meta boxes do not use these keys.
		if ( isset( $aField['page_slug'], $aField['section_id'] ) ) 
			return $this->_getInputFieldValueFromOptionTable( $aField, $aOptions );
		
		// For meta boxes
		if ( isset( $_GET['action'], $_GET['post'] ) ) 
			return $this->_getInputFieldValueFromPostTable( $_GET['post'], $aField );
			
	}	
	
	/**
	 * 
	 * @since			2.0.0
	 * @since			3.0.0			Dropped the check of default values.
	 */
	private function _getInputFieldValueFromOptionTable( &$aField, &$aOptions ) {
		
		if ( ! isset( $aOptions[ $aField['page_slug'] ][ $aField['field_id'] ] ) )
			return;
						
		return $aOptions[ $aField['page_slug'] ][ $aField['field_id'] ];
		
/* // If it's not an array, return it.
if ( ! is_array( $vValue ) && ! is_object( $vValue ) ) return $vValue;

// If it's an array, check if there is an empty value in each element.
$vDefault = isset( $aField['default'] ) ? $aField['default'] : array(); 
foreach ( $vValue as $sKey => &$sElement ) 
	if ( $sElement == '' )
		$sElement = $this->getCorrespondingArrayValue( $vDefault, $sKey, '' );

return $vValue;
 */			
		
	}	
	/**
	 * 
	 * @since			2.0.0
	 * @subce			3.0.0			Dropped the check of default values
	 */
	private function _getInputFieldValueFromPostTable( $iPostID, &$aField ) {
		
		return get_post_meta( $iPostID, $aField['field_id'], true );
		
		// Check if it's not an array return it.
		if ( ! is_array( $vValue ) && ! is_object( $vValue ) ) return $vValue;
		
		// If it's an array, check if there is an empty value in each element.
		$default = isset( $aField['default'] ) ? $aField['default'] : array(); 
		foreach ( $vValue as $sKey => &$sElement ) 
			if ( $sElement == '' )
				$sElement = $this->getCorrespondingArrayValue( $default, $sKey, '' );
		
		return $vValue;
		
	}
		
	private function _getInputTagID( $aField )  {
		
		// For Settings API's form fields should have these key values.
		if ( isset( $aField['section_id'], $aField['field_id'] ) )
			return "{$aField['section_id']}_{$aField['field_id']}";
			
		// For meta box form fields,
		if ( isset( $aField['field_id'] ) ) return $aField['field_id'];
		if ( isset( $aField['name'] ) ) return $aField['name'];	// the name key is for the input name attribute but it's better than nothing.
		
		// Not Found - it's not a big deal to have an empty value for this. It's just for the anchor link.
		return '';
			
	}		
	
	
	/** 
	 * Retrieves the input field HTML output.
	 * @since			2.0.0
	 * @since			2.1.6			Moved the repeater script outside the fieldset tag.
	 */ 
	public function _getInputFieldOutput() {
		
		$aOutput = array();
		
		/* 1. Prepend the field error message. */
		$aOutput[] = isset( $this->aErrors[ $this->aField['field_id'] ] )
			? "<span style='color:red;'>*&nbsp;{$this->aField['error_message']}" . $this->aErrors[ $this->aField['field_id'] ] . "</span><br />"
			: '';		
					
		/* 2. Set new elements */
		$this->aField['field_name'] = $this->_getInputFieldName( $this->aField );
		$this->aField['tag_id'] = $this->_getInputTagID( $this->aField );

		/* 3. Compose fields array for sub-fields	*/
		$aFields = $this->_composeFieldsArray( $this->aField, $this->aOptions );
		
		/* 4. Get the field output. */
		foreach( $aFields as $sKey => $aField ) {
			
			/* 4-1. Set some new elements */ 
			$aField['index'] = $sKey;
			$aField['input_id'] = "{$aField['field_id']}_{$sKey}";
			$aField['field_name'] = $aField['is_multiple'] ? "{$aField['field_name']}[{$sKey}]" : $aField['field_name'];
			$sRepeatable = $this->aField['is_repeatable'] ? 'repeatable' : '';
			
			/* 4-2. Retrieve the field definition for this type - this process enabels to have mixed field types in sub-fields */ 
			$aFieldTypeDefinition = isset( $this->aFieldTypeDefinitions[ $aField['type'] ] )
				? $this->aFieldTypeDefinitions[ $aField['type'] ] 
				: $this->aFieldTypeDefinitions['default'];
				
			$aOutput[] = is_callable( $aFieldTypeDefinition['hfRenderField'] ) 
				? "<div class='admin-page-framework-field admin-page-framework-field-{$aField['type']} {$sRepeatable}' id='field-{$aField['input_id']}' data-type='{$aField['type']}'>"
					. call_user_func_array(
						$aFieldTypeDefinition['hfRenderField'],
						array( $aField )
					)
					. ( ( $sDelimiter = $aField['delimiter'] )
						? "<div class='delimiter' id='delimiter-{$aField['input_id']}'>" . $sDelimiter . "</div>"
						: ""
					)
					. "</div>"
				: "";

		}
				
		/* 5. Add the description */
		$aOutput[] = ( isset( $this->aField['description'] ) && trim( $this->aField['description'] ) != '' ) 
			? "<p class='admin-page-framework-fields-description'><span class='description'>{$this->aField['description']}</span></p>"
			: '';
			
		/* 6. Add the repeater script */
		$aOutput[] = $this->aField['is_repeatable']
			? $this->_getRepeaterScript( $this->aField['tag_id'], count( $aFields ) )
			: '';

		return $this->_getRepeaterScriptGlobal( $this->aField['tag_id'] )
			. "<fieldset>"
				. "<div class='admin-page-framework-fields' id='{$this->aField['tag_id']}'>"
					. $this->aField['before_field'] 
					. implode( PHP_EOL, $aOutput )
					. $this->aField['after_field']
				. "</div>"
			. "</fieldset>";
		
	}
	
		/**
		 * Returns the array of fields 
		 * 
		 * @since			3.0.0
		 */
		protected function _composeFieldsArray( $aField, $aOptions ) {

			/* Get the set value(s) */
			$vSavedValue = $this->_getInputFieldValue( $aField, $aOptions );
		
			/* Separate the first field and sub-fields */
			$aFirstField = array();
			$aSubFields = array();
			foreach( $aField as $nsIndex => $vFieldElement ) {
				if ( is_numeric( $nsIndex ) ) 
					$aSubFields[] = $vFieldElement;
				else 
					$aFirstField[ $nsIndex ] = $vFieldElement;
			}		
			
			/* Create the sub-fields of repeatable fields based on the saved values */
			if ( $aField['is_repeatable'] ) 
				foreach( ( array ) $vSavedValue as $iIndex => $vValue ) {
					if ( $iIndex == 0 ) continue;
					$aSubFields[ $iIndex - 1 ] = isset( $aSubFields[ $iIndex - 1 ] ) && is_array( $aSubFields[ $iIndex - 1 ] ) 
						? $aSubFields[ $iIndex - 1 ] 
						: array();			
				}
			
			/* Put the initial field and the sub-fields together in one array */
			foreach( $aSubFields as &$aSubField ) 
				$aSubField = $aSubField + $aFirstField;
			$aFields = array_merge( array( $aFirstField ), $aSubFields );
					
			/* Set the saved values */		
			if ( count( $aSubFields ) > 0 || $aField['is_repeatable'] || $aField['is_sortable'] ) {	// means the elements are saved in an array.
				foreach( $aFields as $iIndex => &$aThisField ) {
					$aThisField['saved_value'] = isset( $vSavedValue[ $iIndex ] ) ? $vSavedValue[ $iIndex ] : null;
					$aThisField['is_multiple'] = true;
				}
			} else {
				$aFields[ 0 ]['saved_value'] = $vSavedValue;
				$aFields[ 0 ]['is_multiple'] = false;
			} 

			/* Determine the value */
			unset( $aThisField );	// PHP requires this for a previously used variable as reference.
			foreach( $aFields as &$aThisField ) 
				$aThisField['value'] = isset( $aThisField['value'] ) 
					? $aThisField['value'] 
					: ( isset( $aThisField['saved_value'] ) 
						? $aThisField['saved_value']
						: ( isset( $aThisField['default'] )
							? $aThisField['default']
							: null
						)
					);

			return $aFields;
			
		}
	
	/**
	 * Sets or return the flag that indicates whether the creating fields are for meta boxes or not.
	 * 
	 * If the parameter is not set, it will return the stored value. Otherwise, it will set the value.
	 * 
	 * @since			2.1.2
	 */
	public function isMetaBox( $bTrueOrFalse=null ) {
		
		if ( isset( $bTrueOrFalse ) ) 
			$this->_bIsMetaBox = $bTrueOrFalse;
			
		return $this->_bIsMetaBox;
		
	}
	
	/**
	 * Indicates whether the repeatable fields script is called or not.
	 * 
	 * @since			2.1.3
	 */
	private $bIsRepeatableScriptCalled = false;
	
	/**
	 * Returns the repeatable fields script.
	 * 
	 * @since			2.1.3
	 */
	private function _getRepeaterScript( $sTagID, $iFieldCount ) {

		$sAdd = $this->oMsg->__( 'add' );
		$sRemove = $this->oMsg->__( 'remove' );
		$sVisibility = $iFieldCount <= 1 ? " style='display:none;'" : "";
		$sButtons = 
			"<div class='admin-page-framework-repeatable-field-buttons'>"
				. "<a class='repeatable-field-add button-secondary repeatable-field-button button button-small' href='#' title='{$sAdd}' data-id='{$sTagID}'>+</a>"
				. "<a class='repeatable-field-remove button-secondary repeatable-field-button button button-small' href='#' title='{$sRemove}' {$sVisibility} data-id='{$sTagID}'>-</a>"
			. "</div>";

		return
			"<script type='text/javascript'>
				jQuery( document ).ready( function() {
					jQuery( '#{$sTagID} .admin-page-framework-field' ).append( \"{$sButtons}\" );	// Adds the buttons
					updateAPFRepeatableFields( '{$sTagID}' );	// Update the fields					
				});
			</script>";
		
	}

	/**
	 * Returns the script that will be referred multiple times.
	 * since			2.1.3
	 */
	private function _getRepeaterScriptGlobal( $sID ) {

		if ( $this->bIsRepeatableScriptCalled ) return '';
		$this->bIsRepeatableScriptCalled = true;
		return 
		"<script type='text/javascript'>
			jQuery( document ).ready( function() {
				
				// Global function literals
				
				// This function modifies the ids and names of the tags of input, textarea, and relevant tags for repeatable fields.
				updateAPFIDsAndNames = function( nodeElement, fIncrementOrDecrement ) {

					var updateID = function( index, name ) {
						
						if ( typeof name === 'undefined' ) {
							return name;
						}
						return name.replace( /_((\d+))(?=(_|$))/, function ( fullMatch, n ) {						
							return '_' + ( Number(n) + ( fIncrementOrDecrement == 1 ? 1 : -1 ) );
						});
						
					}
					var updateName = function( index, name ) {
						
						if ( typeof name === 'undefined' ) {
							return name;
						}
						return name.replace( /\[((\d+))(?=\])/, function ( fullMatch, n ) {				
							return '[' + ( Number(n) + ( fIncrementOrDecrement == 1 ? 1 : -1 ) );
						});
						
					}					
				
					nodeElement.attr( 'id', function( index, name ) { return updateID( index, name ) } );
					nodeElement.find( 'input,textarea' ).attr( 'id', function( index, name ){ return updateID( index, name ) } );
					nodeElement.find( 'input,textarea' ).attr( 'name', function( index, name ){ return updateName( index, name ) } );
										
					// Color Pickers
					var nodeColorInput = nodeElement.find( 'input.input_color' );
					if ( nodeColorInput.length > 0 ) {
						
							var previous_id = nodeColorInput.attr( 'id' );
							
							if ( fIncrementOrDecrement > 0 ) {	// Add
					
								// For WP 3.5+
								var nodeNewColorInput = nodeColorInput.clone();	// re-clone without bind events.
								
								// For WP 3.4.x or below
								var sInputValue = nodeNewColorInput.val() ? nodeNewColorInput.val() : 'transparent';
								var sInputStyle = sInputValue != 'transparent' && nodeNewColorInput.attr( 'style' ) ? nodeNewColorInput.attr( 'style' ) : '';
								
								nodeNewColorInput.val( sInputValue );	// set the default value	
								nodeNewColorInput.attr( 'style', sInputStyle );	// remove the background color set to the input field ( for WP 3.4.x or below )						 
								
								var nodeFarbtastic = nodeElement.find( '.colorpicker' );
								var nodeNewFarbtastic = nodeFarbtastic.clone();	// re-clone without bind elements.
								
								// Remove the old elements
								nodeIris = jQuery( '#' + previous_id ).closest( '.wp-picker-container' );	
								if ( nodeIris.length > 0 ) {	// WP 3.5+
									nodeIris.remove();	
								} else {
									jQuery( '#' + previous_id ).remove();	// WP 3.4.x or below
									nodeElement.find( '.colorpicker' ).remove();	// WP 3.4.x or below
								}
							
								// Add the new elements
								nodeElement.prepend( nodeNewFarbtastic );
								nodeElement.prepend( nodeNewColorInput );
								
							}
							
							nodeElement.find( '.colorpicker' ).attr( 'id', function( index, name ){ return updateID( index, name ) } );
							nodeElement.find( '.colorpicker' ).attr( 'rel', function( index, name ){ return updateID( index, name ) } );					

							// Renew the color picker script
							var cloned_id = nodeElement.find( 'input.input_color' ).attr( 'id' );
							registerAPFColorPickerField( cloned_id );					
					
					}

					// Image uploader buttons and image preview elements
					image_uploader_button = nodeElement.find( '.select_image' );
					if ( image_uploader_button.length > 0 ) {
						var previous_id = nodeElement.find( '.image-field input' ).attr( 'id' );
						image_uploader_button.attr( 'id', function( index, name ){ return updateID( index, name ) } );
						nodeElement.find( '.image_preview' ).attr( 'id', function( index, name ){ return updateID( index, name ) } );
						nodeElement.find( '.image_preview img' ).attr( 'id', function( index, name ){ return updateID( index, name ) } );
					
						if ( jQuery( image_uploader_button ).data( 'uploader_type' ) == '1' ) {	// for Wordpress 3.5 or above
							var fExternalSource = jQuery( image_uploader_button ).attr( 'data-enable_external_source' );
							setAPFImageUploader( previous_id, true, fExternalSource );	
						}						
					}
					
					// Media uploader buttons
					media_uploader_button = nodeElement.find( '.select_media' );
					if ( media_uploader_button.length > 0 ) {
						var previous_id = nodeElement.find( '.media-field input' ).attr( 'id' );
						media_uploader_button.attr( 'id', function( index, name ){ return updateID( index, name ) } );
					
						if ( jQuery( media_uploader_button ).data( 'uploader_type' ) == '1' ) {	// for Wordpress 3.5 or above
							var fExternalSource = jQuery( media_uploader_button ).attr( 'data-enable_external_source' );
							setAPFMediaUploader( previous_id, true, fExternalSource );	
						}						
					}	
									
				}
				
				// This function is called from the updateAPFRepeatableFields() and from the media uploader for multiple file selections.
				addAPFRepeatableField = function( sFieldContainerID ) {	

					var nodeFieldContainer = jQuery( '#' + sFieldContainerID );
					var nodeNewField = nodeFieldContainer.clone();	// clone without bind events.
					var nodeFieldsContainer = nodeFieldContainer.closest( '.admin-page-framework-fields' );
					
					updateAPFRepeatableFields( nodeNewField );	// Rebind the click event to the buttons
					nodeNewField.find( 'input,textarea' ).val( '' );	// empty the value		
					nodeNewField.find( '.image_preview' ).hide();					// for the image field type, hide the preview element
					nodeNewField.find( '.image_preview img' ).attr( 'src', '' );	// for the image field type, empty the src property for the image uploader field

					// Call the registered callback functions
					nodeNewField.callBackAddRepeatableField( nodeNewField.data( 'type' ), nodeNewField.attr( 'id' ) );					
					
					nodeNewField.insertAfter( nodeFieldContainer );		// add the cloned new field element

					// Increment the names and ids of the next following siblings.
					nodeFieldContainer.nextAll().each( function() {
						updateAPFIDsAndNames( jQuery( this ), true );
					});
		
					var nodeRemoveButtons =  nodeFieldsContainer.find( '.repeatable-field-remove' );
					if ( nodeRemoveButtons.length > 1 ) 
						nodeRemoveButtons.show();				
										
					// Return the newly created element
					return nodeNewField;
					
				}
				
				// This function gets triggered when the document becomes ready.
				updateAPFRepeatableFields = function( vTagIDOrNode ) {
				
					var nodeAddButtons = ( typeof vTagIDOrNode == 'string' || vTagIDOrNode instanceof String )
						? jQuery( '#' + vTagIDOrNode + ' .repeatable-field-add' )
						: vTagIDOrNode.find( '.repeatable-field-add' );
				
					// Add button behaviour
					nodeAddButtons.click( function() {
						var nodeFieldContainer = jQuery( this ).closest( '.admin-page-framework-field' );
						addAPFRepeatableField( nodeFieldContainer.attr( 'id' ) );
						return false;
					});		
					
					// Remove button behaviour
					var nodeRemoveButtons = ( typeof vTagIDOrNode == 'string' || vTagIDOrNode instanceof String )
						? jQuery( '#' + vTagIDOrNode + ' .repeatable-field-remove' )
						: vTagIDOrNode.find( '.repeatable-field-remove' );
					nodeRemoveButtons.click( function() {
						
						// Need to remove two elements: the field container and the delimiter element.
						var nodeFieldContainer = jQuery( this ).closest( '.admin-page-framework-field' );
						var nodeFieldContainer_id = nodeFieldContainer.attr( 'id' );				

						// Decrement the names and ids of the next following siblings.
						nodeFieldContainer.nextAll().each( function() {
							updateAPFIDsAndNames( jQuery( this ), false );	// the second parameter value indicates it's for decrement.
						});

						nodeFieldContainer.remove();

						var nodeRemoveButtons = ( typeof vTagIDOrNode == 'string' || vTagIDOrNode instanceof String )
							? jQuery( '#' + vTagIDOrNode + ' .repeatable-field-remove' )
							: vTagIDOrNode.find( '.repeatable-field-remove' );						
						var iFieldsCount = nodeRemoveButtons.length;
						if ( iFieldsCount == 1 ) {
							nodeRemoveButtons.css( 'display', 'none' );
						}
						return false;
					});
									
				}
			});
		</script>";
	}

	/**
	 * @since			3.0.0
	 */
	public function _replyToAddRegisterCallbackScript() {
		
		$sScript = 
			"
(function ( $ ) {
	
	// The method that gets triggered when a repeatable field add button is pressed.
	$.fn.callBackAddRepeatableField = function( sFieldType, sID ) {
		var nodeThis = this;
		$.fn.aAPFAddRepeatableFieldCallbacks.forEach( function( hfCallback ) {
			if ( jQuery.isFunction( hfCallback ) )
				hfCallback( nodeThis, sFieldType, sID );
		});
	};
	
	$.fn.registerAPFCallback = function( oOptions ) {
		// This is the easiest way to have default options.
		var oSettings = $.extend({
			// These are the defaults.
			color: '#556b2f',
			backgroundColor: 'white',
			added_repeatable_field: function() {},
			
		}, oOptions );

		if( ! $.fn.aAPFAddRepeatableFieldCallbacks ){
			$.fn.aAPFAddRepeatableFieldCallbacks = [];
		}
		
		// Store the callback function
		$.fn.aAPFAddRepeatableFieldCallbacks.push( oSettings.added_repeatable_field );
		
		// Greenify the collection based on the settings variable.
		return this.css({
			color: oSettings.color,
			backgroundColor: oSettings.backgroundColor
		});
	};
	
}( jQuery ));			
			";
		echo "<script type='text/javascript' class='admin-page-framework-register-callback'>{$sScript}</script>";

		
	}
}
endif;