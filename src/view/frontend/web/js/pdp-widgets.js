define([
    'uiComponent',
    'jquery',
], function (Component, $) {
    'use strict';

    return Component.extend({
        defaults: {
            containerId: null,
            widgetName: null,
            currency: 'GBP',
            initialPrice: null,
            rvvupWidgetInstance: null,
        },
        initialize: function (config) {
            this.containerId = config.containerId;
            this.widgetName = config.widgetName;
            this.initialPrice = config.initialPrice;
            this.currency = config.currency || "GBP";
            if (this.containerId === null) {
                return this;
            }
            if (this.widgetName === null) {
                return this;
            }
            if (this.initialPrice === null) {
                return this;
            }
            this.render();

        },
        render: function () {
            let $self = this;
            let getFinalAmount = function (data) {
                // Magento versions differ a bit in the payload shape, so we get it from both possible locations
                return (data && data.finalPrice && data.finalPrice.amount)
                    || (data && data.prices && data.prices.finalPrice && data.prices.finalPrice.amount)
                    || null;
            }

            let showHideWidget = async function (el, rvvupWidgetInstance) {
                if (!(await rvvupWidgetInstance.canDisplayWidget())) {
                    el.style.display = "none";
                    return;
                }

                el.style.display = "block";
            }
            window.rvvup_sdk.createWidget($self.widgetName, {
                settings: {
                    context: "product"
                },
                total: {
                    currency: $self.currency,
                    amount: $self.initialPrice.toString()
                },
            }).then((widget) => {
                this.rvvupWidgetInstance = widget;
                this.rvvupWidgetInstance.on("ready", async () => {
                    const el = document.getElementById($self.containerId);
                    if (!el) return;
                    await this.rvvupWidgetInstance.mount({selector: el});
                    await showHideWidget(el, $self.rvvupWidgetInstance);
                });
            }).catch((error) => {
                console.error("Error creating Rvvup widget:", error);
            });
            $(document).on('priceUpdated', '.product-info-main .price-box', function (event, data) {
                let amount = getFinalAmount(data);
                if (amount == null) return;
                if (!$self.rvvupWidgetInstance) return;
                const el = document.getElementById($self.containerId);
                if (!el) return;
                $self.rvvupWidgetInstance.update({
                    total: {
                        currency: $self.currency,
                        amount: amount.toString()
                    }
                });
                showHideWidget(el, $self.rvvupWidgetInstance).then(() => {
                });
            });
        }
    });
});
