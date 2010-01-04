Ext.namespace('ui','ui.task','ui.task._SystemUpdateTask');

ui.task._SystemUpdateTask.refresh_ui = function()
{
    Ext.get('wizard-step-3').replaceClass('wizard-step-before', 'wizard-step-working');

    phpDoc.reloadAllStore();

    Ext.get('wizard-step-3').replaceClass('wizard-step-working', 'wizard-step-done');

    // Re-enable Finish button
    Ext.getCmp('btn-start-refresh').setIconClass('iconFinishRefresh');
    Ext.getCmp('btn-start-refresh').setText(_('Finish !'));
    Ext.getCmp('btn-start-refresh').setHandler(function()
    {
        Ext.getCmp('sys-update-win').close();
    });
    Ext.getCmp('btn-start-refresh').enable();

    // Re-enable TaskPing
    ui.task.PingTask.getInstance().delay(30000);

    // Re-enable win's close button
    Ext.getCmp('sys-update-win').tools.close.setVisible(true);
};

ui.task._SystemUpdateTask.poll_apply_tool = new Ext.util.DelayedTask(function()
{
    XHR({
        params  : {
            task     : 'checkLockFile',
            lockFile : 'project_' + phpDoc.project + '_lock_apply_tools'
        },
        success : function(response)
        {
            ui.task._SystemUpdateTask.poll_apply_tool.delay(5000);
        },
        failure : function(response)
        {
            var o = Ext.util.JSON.decode(response.responseText), tmp;
            if (o && o.success === false) {
                Ext.get('wizard-step-2').replaceClass('wizard-step-working', 'wizard-step-done');
                tmp = new ui.task._SystemUpdateTask.refresh_ui();
            } else {
                ui.task._SystemUpdateTask.poll_apply_tool.delay(5000);
            }
        }
    });
});

ui.task._SystemUpdateTask.apply_tool = function()
{
    Ext.get('wizard-step-2').replaceClass('wizard-step-before', 'wizard-step-working');
    XHR({
        params  : {
            task: 'applyTools'
        },
        success : function(response)
        {
            var tmp;
            Ext.get('wizard-step-2').replaceClass('wizard-step-working', 'wizard-step-done');
            tmp = new ui.task._SystemUpdateTask.refresh_ui();
        },
        failure : function(response)
        {
            ui.task._SystemUpdateTask.poll_apply_tool.delay(5000);
        }
    });
};

ui.task._SystemUpdateTask.vcs_poll = new Ext.util.DelayedTask(function()
{
    XHR({
        params  : {
            task     : 'checkLockFile',
            lockFile : 'project_' + phpDoc.project + '_lock_update_repository'
        },
        success : function(response)
        {
            ui.task._SystemUpdateTask.vcs_poll.delay(5000);
        },
        failure : function(response)
        {
            var o = Ext.util.JSON.decode(response.responseText), tmp;

            if (o && o.success === false) {
                Ext.get('wizard-step-1').replaceClass('wizard-step-working', 'wizard-step-done');
                Ext.get('wizard-step-1.1').replaceClass('wizard-show', 'wizard-wait');

                tmp = new ui.task._SystemUpdateTask.apply_tool();
            } else {
                ui.task._SystemUpdateTask.vcs_poll.delay(5000);
            }
        }
    });
});

ui.task.SystemUpdateTask = function()
{
    ui.task.PingTask.getInstance().cancel();

    Ext.get('wizard-step-1').replaceClass('wizard-step-before', 'wizard-step-working');
    Ext.get('wizard-step-1.1').replaceClass('wizard-wait', 'wizard-show');

    XHR({
        params  : { task: 'updateRepository' },
        success : function(response)
        {
            var tmp;
            Ext.get('wizard-step-1').replaceClass('wizard-step-working', 'wizard-step-done');
            Ext.get('wizard-step-1.1').replaceClass('wizard-show', 'wizard-wait');

            tmp = new ui.task._SystemUpdateTask.apply_tool();
        },
        failure: function(response)
        {
            var o = Ext.util.JSON.decode(response.responseText);

            if (o && o.success === false) {
                Ext.getCmp('sys-update-win').close();
                phpDoc.winForbidden();
            } else {
                ui.task._SystemUpdateTask.vcs_poll.delay(5000);
            }
        }
    });
};
