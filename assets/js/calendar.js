/**
 * FinTrack Pro - Calendar & Schedule Controller (Phase 4)
 */

import { formatCurrency } from './utils.js';

const BILLS_STORAGE_KEY = 'fintrack_bills_v1';
const LOANS_STORAGE_KEY = 'fintrack_loans_v1';

let scheduledEvents = [];

export function initCalendarPage() {
  const isCalendarPage = document.querySelector('.calendar-page-layout');
  if (!isCalendarPage) return;

  console.warn("Initializing Schedule Calendar...");

  loadScheduledEvents();
  renderCalendar();
}

function loadScheduledEvents() {
  scheduledEvents = [];

  // Load Bills
  const billsData = localStorage.getItem(BILLS_STORAGE_KEY);
  if (billsData) {
    try {
      const bills = JSON.parse(billsData);
      bills.forEach(bill => {
        scheduledEvents.push({
          date: bill.date,
          title: `Bill: ${bill.name}`,
          amount: bill.amount,
          type: 'bill',
          status: bill.status
        });
      });
    } catch (e) { console.error(e); }
  }

  // Load Loans for EMIs
  const loansData = localStorage.getItem(LOANS_STORAGE_KEY);
  if (loansData) {
    try {
      const loans = JSON.parse(loansData);
      loans.forEach(loan => {
        // Mock EMI due on 10th of current month
        const today = new Date();
        const year = today.getFullYear();
        const month = String(today.getMonth() + 1).padStart(2, '0');
        
        scheduledEvents.push({
          date: `${year}-${month}-10`,
          title: `EMI: ${loan.lender}`,
          amount: loan.emi,
          type: 'emi',
          status: 'pending'
        });
      });
    } catch (e) { console.error(e); }
  }
}

function renderCalendar() {
  const grid = document.getElementById('calendarDaysGrid');
  const monthText = document.getElementById('calendarMonthYear');
  if (!grid || !monthText) return;

  grid.innerHTML = '';
  
  // Set current month = July 2026
  const targetYear = 2026;
  const targetMonthIdx = 6; // July (0-indexed)
  monthText.textContent = "July 2026";

  // Get first day of July 2026: Wednesday (day index 3)
  const startDayOffset = 3;
  const totalDays = 31;

  // Add blank padding cells for offset days
  for (let i = 0; i < startDayOffset; i++) {
    const blank = document.createElement('div');
    blank.className = 'calendar-day blank';
    grid.appendChild(blank);
  }

  // Populate days
  for (let day = 1; day <= totalDays; day++) {
    const dayCell = document.createElement('div');
    dayCell.className = 'calendar-day';
    dayCell.innerHTML = `<span class="day-number">${day}</span>`;

    // Match day events
    const currentDateStr = `2026-07-${String(day).padStart(2, '0')}`;
    const dayEvents = scheduledEvents.filter(e => e.date === currentDateStr);

    if (dayEvents.length > 0) {
      const dotsWrapper = document.createElement('div');
      dotsWrapper.className = 'day-events-dots';
      
      dayEvents.forEach(evt => {
        const dot = document.createElement('span');
        dot.className = `event-dot dot-${evt.type} dot-${evt.status}`;
        dot.setAttribute('data-tooltip', `${evt.title} (${formatCurrency(evt.amount)})`);
        dotsWrapper.appendChild(dot);
      });
      dayCell.appendChild(dotsWrapper);
      dayCell.classList.add('has-events');
    }

    grid.appendChild(dayCell);
  }
}
