/* event fired when a field changes value, click to insert helper code into your js file. */
$('#cf7sg-form-testform form.wpcf7-form').on( 'change',':input', function(e){
  let $form = $(e.delegateTarget), $field=$(this), fieldName = $field.attr('name');

  // $form is the form jquery object.
  // $field is the input field jquery object.
  //
  switch(fieldName){
    case 'ams-foerderung': //ams-foerderung updated.
      //do something
      break;
    case 'waff-foerderung': //waff-foerderung updated.
      //do something
      break;
  }
});
