$(document).ready(function() {
    console.log( "ready!" );
    $("#js-disabled").hide();
    $("#check-all").show();
    $("#check-none").show();

    $("#check-all").click(function(){
        $(".strain-checkbox").prop( "checked", true );
    });

    $("#check-none").click(function(){
        $(".strain-checkbox").prop( "checked", false );
    });
});
