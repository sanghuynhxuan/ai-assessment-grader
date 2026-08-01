jQuery(document).ready(function($) {
    if(!$('#chartDailyUsage').length) return;

    $.ajax({
        url: ldAiAdminVars.apiUrl,
        beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', ldAiAdminVars.nonce); },
        success: function(data) {
            // Render Chart 1: Daily
            new Chart(document.getElementById('chartDailyUsage'), {
                type: 'line',
                data: {
                    labels: data.chart_daily.labels,
                    datasets: [{
                        label: 'Submissions',
                        data: data.chart_daily.data,
                        borderColor: '#2271b1',
                        fill: true
                    }]
                }
            });

            // Render Chart 2: Provider
            let pLabels = data.chart_provider.map(i => i.provider);
            let pData = data.chart_provider.map(i => i.count);
            new Chart(document.getElementById('chartProviderDist'), {
                type: 'doughnut',
                data: { labels: pLabels, datasets: [{ data: pData }] }
            });
            
            // Update Summary Text
            $('#stat-total-graded').text(data.summary.total_graded);
            $('#stat-total-cost').text(data.summary.total_cost);
            $('#stat-avg-score').text(data.summary.avg_score);
            
            // Render Table
            let rows = '';
            data.recent_logs.forEach(l => {
                rows += `<tr><td>${l.id}</td><td>${l.provider}</td><td>${l.model}</td><td>${l.ai_score}</td><td>${l.cost_estimated}</td><td>${l.created_at}</td></tr>`;
            });
            $('#table-recent-logs tbody').html(rows);
        }
    });
});