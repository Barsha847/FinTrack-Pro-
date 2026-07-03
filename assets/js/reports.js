/**
 * FinTrack Pro - Reports & Export System Controller (Phase 4)
 */

import { showToast, formatCurrency } from './utils.js';

const TX_STORAGE_KEY = 'fintrack_transactions_v1';

let transactionsList = [];

export function initReportsPage() {
  const isReportsPage = document.querySelector('.reports-page-layout');
  if (!isReportsPage) return;

  console.warn("Initializing Reports & Export Module...");

  loadReportData();
  setupEventListeners();
}

function loadReportData() {
  const data = localStorage.getItem(TX_STORAGE_KEY);
  if (data) {
    try { transactionsList = JSON.parse(data); } catch (e) { transactionsList = []; }
  } else {
    transactionsList = [
      { id: 1, title: 'Regular Salary Credited', amount: 85000.00, type: 'income', date: '2026-06-28', category: 'salary' },
      { id: 2, title: 'Whole Foods Outflow', amount: 4520.00, type: 'expense', date: '2026-06-27', category: 'food' },
      { id: 3, title: 'AWS Cloud Hosting Invoice', amount: 1850.00, type: 'expense', date: '2026-06-26', category: 'bills' }
    ];
  }
}

function setupEventListeners() {
  const exportBtn = document.getElementById('initiateExportBtn');
  if (!exportBtn) return;

  exportBtn.addEventListener('click', () => {
    const format = document.getElementById('reportFormat').value;
    const dateRange = document.getElementById('reportDateRange').value;

    // Filter by dates if applicable (mock filtering for demo)
    let filtered = [...transactionsList];
    const today = new Date();
    
    if (dateRange === '30days') {
      const boundary = new Date(today.setDate(today.getDate() - 30));
      filtered = transactionsList.filter(t => new Date(t.date) >= boundary);
    } else if (dateRange === '90days') {
      const boundary = new Date(today.setDate(today.getDate() - 90));
      filtered = transactionsList.filter(t => new Date(t.date) >= boundary);
    }

    if (filtered.length === 0) {
      showToast("No transaction records found matching this date range.", "warning", "Export Cancelled");
      return;
    }

    // Trigger format-specific download logic
    if (format === 'csv') {
      triggerCSVDownload(filtered);
    } else if (format === 'json') {
      triggerJSONDownload(filtered);
    } else if (format === 'pdf') {
      triggerPDFPrint();
    }
  });
}

function triggerCSVDownload(data) {
  let csvContent = 'Date,Description,Category,Type,Amount\n';
  data.forEach(item => {
    csvContent += `"${item.date}","${item.title.replace(/"/g, '""')}","${item.category}","${item.type}",${item.amount}\n`;
  });

  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.setAttribute('href', url);
  link.setAttribute('download', `fintrack_report_${Date.now()}.csv`);
  link.style.visibility = 'hidden';
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);

  showToast("CSV Ledger spreadsheet downloaded successfully.", "success", "Report Exported");
}

function triggerJSONDownload(data) {
  const jsonContent = JSON.stringify(data, null, 2);
  const blob = new Blob([jsonContent], { type: 'application/json;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.setAttribute('href', url);
  link.setAttribute('download', `fintrack_report_${Date.now()}.json`);
  link.style.visibility = 'hidden';
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);

  showToast("JSON Ledger schema downloaded successfully.", "success", "Report Exported");
}

function triggerPDFPrint() {
  showToast("Launching browser print interface for PDF download...", "info", "Printing Report");
  setTimeout(() => {
    window.print();
  }, 1000);
}
