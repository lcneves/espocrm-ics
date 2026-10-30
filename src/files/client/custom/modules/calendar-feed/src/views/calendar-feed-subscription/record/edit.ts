import EditRecordView from 'views/record/edit';

class CalendarFeedSubscriptionEditView extends EditRecordView {

    afterSave() {
        const url = this.model.get('subscriptionUrl');
        const created = this.isNew;

        super.afterSave();

        if (created && url) {
            this.createView('subscriptionUrl', 'modules/calendar-feed/views/calendar-feed-subscription/modals/url', {
                url: url,
            }, view => {
                view.render();
            });
        }
    }
}

export default CalendarFeedSubscriptionEditView;
