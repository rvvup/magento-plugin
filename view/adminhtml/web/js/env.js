require(['domReady!'], function () {
    document.getElementsByName('groups[rvvup][fields][jwt][value]').forEach(function (element) {
        element.addEventListener('change', function (event) {
            const parts = event.target.value.split('.')
            if (parts.length === 3) {
                const payload = atob(parts[1])
                const json = JSON.parse(payload)
                if (json.hasOwnProperty('live')) {
                    switch (json.live) {
                        case true:
                            setEnvMessage(element, 'API key entered is for PRODUCTION', 'success')
                            break
                        case false:
                            setEnvMessage(element, 'API key entered is for SANDBOX', 'success')
                            break
                        default:
                            setEnvMessage(element, 'API key entered environment is UNKNOWN', 'error')
                    }
                } else {
                    setEnvMessage(element, 'API key entered environment is UNKNOWN', 'error')
                }
            } else {
                setEnvMessage(element, 'Value entered is not a valid API key', 'error')
            }
        })
    })
    function setEnvMessage(element, message, type) {
        const existing = document.getElementById("rvvup_api_environment");
        if (existing) {
            existing.remove();
        }
        element.insertAdjacentHTML('afterend', '<div id="rvvup_api_environment" class="message message-' + type + ' rvvup-credentials-success-message">' + message + '</div>')
    }
});
