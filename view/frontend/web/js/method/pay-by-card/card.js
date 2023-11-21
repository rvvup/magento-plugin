require(['domReady!'],
    function () {
    let rvvupCardScript = window.rvvup_card_script;
    require([rvvupCardScript], function(externalScript) {
        window.SecureTrading = externalScript;
    });
});
