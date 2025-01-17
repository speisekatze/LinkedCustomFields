<?php
/**
 * Linked custom fields plugin for MantisBT
 *
 * Copyright (c) 2011 Robert Munteanu (robert@lmn.ro)
 * Copyright (c) 2018, 2022 Damien Regad
 *
 * Linked custom fields for MantisBT is free software:
 * you can redistribute it and/or modify it under the terms of the GNU
 * General Public License as published by the Free Software Foundation,
 * either version 2 of the License, or (at your option) any later version.
 *
 * Linked custom fields plugin for MantisBT is distributed in the hope
 * that it will be useful, but WITHOUT ANY WARRANTY; without even the
 * implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Linked custom fields plugin for MantisBT.
 * If not, see <http://www.gnu.org/licenses/>.
 */

header( "Content-Type: text/javascript" );

$t_bug_id = gpc_get_int( 'bug_id' );
$t_master_bug_id = gpc_get_int( 'm_id', 0 );
if( $t_bug_id == 0 ) {
	if( $t_master_bug_id != 0 ) {
		$t_project_id = bug_get_field( $t_master_bug_id, 'project_id' );
	} else {
		$t_project_id = helper_get_current_project();
	}
} else {
	$t_project_id = bug_get_field( $t_bug_id, 'project_id' );
}

$t_all_custom_field_ids = custom_field_get_linked_ids( $t_project_id );

?>
var linkedFieldValues = {};
var allFieldValues = {};
var bindings = {};
var savedValues = {};
<?php

foreach( $t_all_custom_field_ids as $t_custom_field_id ) {

	if( $t_bug_id != 0 ) {
		$t_has_write_access = custom_field_has_write_access( $t_custom_field_id, $t_bug_id );
	} else {
		$t_has_write_access = custom_field_has_write_access_to_project( $t_custom_field_id, $t_project_id );
	}

	if( ! $t_has_write_access ) {
		continue;
	}

	$t_linked_values = LinkedCustomFieldsDao::getLinkedValuesMap( $t_custom_field_id );

	if( count( $t_linked_values ) > 0 ) {

		// values from $t_custom_field_id trigger filter values from $t_linked_field_id

		$t_linked_field_id = LinkedCustomFieldsDao::getLinkedFieldId( $t_custom_field_id );

		$t_linked_field = custom_field_get_definition( $t_linked_field_id );

		$t_saved_values_source_id = $t_master_bug_id != 0 ? $t_master_bug_id : $t_bug_id;
		$t_saved_values = $t_saved_values_source_id != 0 ? explode('|', custom_field_get_value(  $t_linked_field_id, $t_saved_values_source_id ) ) : array();

		echo 'savedValues["' . $t_linked_field_id .'"] = ' . JavascriptUtils::toJSArray( $t_saved_values ).";\n";
		echo 'bindings["' . $t_custom_field_id.'"] = "'. $t_linked_field_id.'";'."\n";
		echo 'allFieldValues["' .$t_custom_field_id.'"] = ' . JavascriptUtils::toJSArray( explode('|', $t_linked_field['possible_values']) ).";\n";
		echo 'linkedFieldValues["'.$t_custom_field_id."\"] = {};\n";

		foreach( $t_linked_values as $t_source_value => $t_target_values ) {
			echo 'linkedFieldValues["' . $t_custom_field_id . '"]["' . addslashes( $t_source_value ) . '"] = '
				. JavascriptUtils::toJSArray( $t_target_values ).";\n";
		}
	}
}
?>

var LinkedCustomFieldsUtil = {
	removeDuplicates : function(arr) {
		var uniques = [];
		for(var i=arr.length;i--;){
			var val = arr[i];
			if(jQuery.inArray( val, uniques )===-1){
				uniques.unshift(val);
			}
		}
		return uniques;
	},
	findCustomFieldByFieldId: function(fieldId) {

		var fieldRef = jQuery('[name="custom_field_' + fieldId  +'"]');
		if ( fieldRef.length == 0 ) {
			fieldRef = jQuery('[name="custom_field_' + fieldId  +'[]"]');
		}

		return fieldRef;
	}
};

var refreshLinkedValues = function(fieldId, fieldValue) {
	var targetValues = [];

	if ( fieldValue instanceof Array) {
		for ( var i = 0 ; i < fieldValue.length; i++) {
			var singleValue = fieldValue[i];
			var currentTargetValues = linkedFieldValues[fieldId][singleValue] || [];
			for ( var j = 0 ; j < currentTargetValues.length; j++ ) {
				targetValues.push(currentTargetValues[j]);
			}
		}

		targetValues = LinkedCustomFieldsUtil.removeDuplicates(targetValues);
	} else {
		targetValues = linkedFieldValues[fieldId][fieldValue];
	}

	if ( ! targetValues || targetValues.length == 0 ) {
		targetValues = allFieldValues[fieldId] ;
	}

	var targetFieldId = bindings[fieldId];

	var targetFieldRef = LinkedCustomFieldsUtil.findCustomFieldByFieldId ( targetFieldId );

	targetFieldRef.empty();
	for ( var i = 0 ; i < targetValues.length; i++ ) {
		var targetValue = targetValues[i];
		targetFieldRef.append(jQuery('<option></option>').
			attr('value', targetValue).
			text(targetValue));
	}

	targetFieldRef.val(savedValues[targetFieldId]);
	/* Update JQueryChosen on new Data */
	$('#custom_field_'+targetFieldId).trigger('chosen:updated');
};

jQuery(document).ready(function() {
	for ( var boundKey in bindings ) {

		var applicable = linkedFieldValues[boundKey];

		var customFieldRef = LinkedCustomFieldsUtil.findCustomFieldByFieldId( boundKey );
		customFieldRef.data('fieldId', boundKey);

		refreshLinkedValues(boundKey, customFieldRef.val());

		customFieldRef.change(function() {
			refreshLinkedValues(jQuery(this).data('fieldId'), jQuery(this).val());
		});
	}
});
