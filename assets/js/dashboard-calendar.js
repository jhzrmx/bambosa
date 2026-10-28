/**
 * Dashboard calendar widget.
 *
 * Renders a month grid inside #dashCalendar, using the events for the
 * initial month embedded server-side as window.BAMBOSA_CALENDAR_INITIAL
 * (see the inline <script> in member/dashboard.php and
 * officer/dashboard.php), then fetches subsequent months from
 * calendar_data.php (project root) as the user navigates with the prev/next
 * buttons — no full page reload needed to change months.
 *
 * Expects on window.BAMBOSA_CALENDAR_INITIAL:
 *   {
 *     year: 2026, month: 9,
 *     events_by_day: { "17": [ { type, title, detail, time, status, date } ], ... },
 *     data_url: '.../calendar_data.php',
 *     today: { year: 2026, month: 9, day: 17 }
 *   }
 */
(function () {
  function ready(fn) {
    if (document.readyState !== 'loading') fn();
    else document.addEventListener('DOMContentLoaded', fn);
  }

  var MONTH_NAMES = ['January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'];
  var WEEKDAY_LABELS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

  function daysInMonth(year, month) {
    // month is 1-indexed here; day 0 of "next month" is the last day of this one.
    return new Date(year, month, 0).getDate();
  }

  function firstWeekday(year, month) {
    return new Date(year, month - 1, 1).getDay();
  }

  ready(function () {
    var root = document.getElementById('dashCalendar');
    if (!root || !window.BAMBOSA_CALENDAR_INITIAL) return;

    var escapeHtml = window.BAMBOSA.escapeHtml;
    var config = window.BAMBOSA_CALENDAR_INITIAL;
    var state = {
      year: config.year,
      month: config.month,
      eventsByDay: config.events_by_day || {},
      loading: false,
    };

    var headerEl = root.querySelector('[data-cal-header]');
    var gridEl = root.querySelector('[data-cal-grid]');
    var detailEl = root.querySelector('[data-cal-detail]');
    var prevBtn = root.querySelector('[data-cal-prev]');
    var nextBtn = root.querySelector('[data-cal-next]');
    var todayBtn = root.querySelector('[data-cal-today]');

    function typeClass(type) {
      return type === 'reservation' ? 'cal-dot-reservation' : 'cal-dot-schedule';
    }

    function renderDetail(dayNum, events) {
      if (!dayNum) {
        detailEl.innerHTML = '<p class="muted-note">Click a day to see what\'s happening.</p>';
        return;
      }
      var label = MONTH_NAMES[state.month - 1] + ' ' + dayNum + ', ' + state.year;
      if (!events || events.length === 0) {
        detailEl.innerHTML = '<h3 class="cal-detail-heading">' + label + '</h3><p class="muted-note">Nothing scheduled.</p>';
        return;
      }
      var html = '<h3 class="cal-detail-heading">' + label + '</h3><ul class="cal-detail-list">';
      events.forEach(function (ev) {
        html += '<li class="cal-detail-item">'
          + '<span class="cal-dot ' + typeClass(ev.type) + '"></span>'
          + '<div>'
          + '<div class="cal-detail-title">' + escapeHtml(ev.title) + '</div>'
          + (ev.time ? '<div class="cal-detail-time">' + escapeHtml(ev.time) + '</div>' : '')
          + '<div class="cal-detail-desc">' + escapeHtml(ev.detail) + '</div>'
          + '</div>'
          + '</li>';
      });
      html += '</ul>';
      detailEl.innerHTML = html;
    }

    function render() {
      headerEl.textContent = MONTH_NAMES[state.month - 1] + ' ' + state.year;

      var totalDays = daysInMonth(state.year, state.month);
      var startWeekday = firstWeekday(state.year, state.month);
      var isCurrentMonth = config.today && state.year === config.today.year && state.month === config.today.month;

      var html = '';
      WEEKDAY_LABELS.forEach(function (label) {
        html += '<div class="cal-weekday">' + label + '</div>';
      });

      for (var i = 0; i < startWeekday; i++) {
        html += '<div class="cal-cell cal-cell-empty"></div>';
      }

      for (var day = 1; day <= totalDays; day++) {
        var events = state.eventsByDay[day] || state.eventsByDay[String(day)] || [];
        var isToday = isCurrentMonth && day === config.today.day;
        var dotsHtml = '';
        var shown = events.slice(0, 3);
        shown.forEach(function (ev) {
          dotsHtml += '<span class="cal-dot ' + typeClass(ev.type) + '" title="' + escapeHtml(ev.title) + '"></span>';
        });
        if (events.length > 3) {
          dotsHtml += '<span class="cal-dot-more">+' + (events.length - 3) + '</span>';
        }

        html += '<button type="button" class="cal-cell' + (isToday ? ' cal-cell-today' : '') + (events.length ? ' cal-cell-has-events' : '') + '" data-cal-day="' + day + '">'
          + '<span class="cal-daynum">' + day + '</span>'
          + '<span class="cal-dots">' + dotsHtml + '</span>'
          + '</button>';
      }

      gridEl.innerHTML = html;

      gridEl.querySelectorAll('[data-cal-day]').forEach(function (cell) {
        cell.addEventListener('click', function () {
          gridEl.querySelectorAll('.cal-cell-selected').forEach(function (c) { c.classList.remove('cal-cell-selected'); });
          cell.classList.add('cal-cell-selected');
          var d = parseInt(cell.getAttribute('data-cal-day'), 10);
          renderDetail(d, state.eventsByDay[d] || state.eventsByDay[String(d)] || []);
        });
      });

      // Auto-select today (or the 1st, if navigated to a different month) so
      // the detail panel always has something relevant to show.
      var autoDay = isCurrentMonth ? config.today.day : null;
      if (autoDay && gridEl.querySelector('[data-cal-day="' + autoDay + '"]')) {
        gridEl.querySelector('[data-cal-day="' + autoDay + '"]').click();
      } else {
        renderDetail(null, null);
      }
    }

    function goToMonth(year, month) {
      if (state.loading) return;

      // Normalize month overflow/underflow (e.g. month 0 -> previous December).
      if (month < 1) { month = 12; year -= 1; }
      if (month > 12) { month = 1; year += 1; }

      if (year === state.year && month === state.month) return;

      state.loading = true;
      root.classList.add('cal-loading');

      fetch(config.data_url + '?year=' + year + '&month=' + month, { credentials: 'same-origin' })
        .then(function (res) {
          if (!res.ok) throw new Error('Failed to load calendar data');
          return res.json();
        })
        .then(function (data) {
          state.year = data.year;
          state.month = data.month;
          state.eventsByDay = data.events_by_day || {};
          render();
        })
        .catch(function () {
          detailEl.innerHTML = '<p class="muted-note">Couldn\'t load that month. Please try again.</p>';
        })
        .finally(function () {
          state.loading = false;
          root.classList.remove('cal-loading');
        });
    }

    prevBtn.addEventListener('click', function () { goToMonth(state.year, state.month - 1); });
    nextBtn.addEventListener('click', function () { goToMonth(state.year, state.month + 1); });
    if (todayBtn) {
      todayBtn.addEventListener('click', function () {
        if (config.today) goToMonth(config.today.year, config.today.month);
      });
    }

    render();
  });
})();
