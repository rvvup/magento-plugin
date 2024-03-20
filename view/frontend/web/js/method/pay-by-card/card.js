require(['domReady!'],
    function () {
    let rvvupCardScript = window.rvvup_card_script;
    if (rvvupCardScript) {
        require([rvvupCardScript], function(externalScript) {
            window.SecureTrading = externalScript;
        });
    }
});
