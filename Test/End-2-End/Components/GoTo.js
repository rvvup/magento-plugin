export default class GoTo {
    constructor(page) {
        this.page = page;
        this.product = new GoToProduct(page);
    }

    async checkout() {
        await this.page.goto('./checkout');
    }

    async cart() {
        await this.page.goto('./checkout/cart');
    }



}
class GoToProduct {
    constructor(page) {
        this.page = page;
    }

    async standard() {
        await this.page.goto('./affirm-water-bottle.html');
    }
}
