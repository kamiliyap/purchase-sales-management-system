
@page { size: A4 portrait; margin: 16mm 14mm 19mm; }
body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; line-height: 1.45; color: #182535; background: #fff; }
table { border-collapse: collapse; width: 100%; }
.kop { border-bottom: 2px solid #233f60; margin-bottom: 19px; }
.kop td { vertical-align: top; padding: 0 0 12px; }
.company { width: 68%; } .company-name { font-size: 19px; font-weight: bold; color: #152d49; line-height: 1.25; }
.business { font-size: 11px; margin-top: 4px; } .simulation { font-size: 7px; color: #526170; margin-top: 6px; }
.contacts { text-align: right; font-size: 8px; color: #35475a; } .contacts div { margin-bottom: 3px; }
.logo { max-height: 40px; max-width: 48px; margin-bottom: 6px; }
.title-row { margin-bottom: 15px; } .title-row td { vertical-align: middle; }
h1 { font-size: 23px; line-height: 1.2; letter-spacing: 0.4px; margin: 0 0 5px; color: #152d49; }
.subtitle { font-size: 8px; letter-spacing: 2.3px; color: #526170; }
.number-cell { width: 36%; padding-left: 12px; } .number-box { background: #eef3f8; border: 1px solid #cbd6e2; border-left: 3px solid #233f60; padding: 10px 11px; }
.label { font-size: 8px; color: #526170; margin-bottom: 3px; } .document-number { font-size: 16px; font-weight: bold; color: #152d49; overflow-wrap: break-word; word-wrap: break-word; }
.meta { table-layout: fixed; margin-bottom: 18px; border: 1px solid #cbd6e2; }
.meta td { width: 50%; padding: 9px 12px; vertical-align: top; border: 1px solid #d8e0e8; }
.meta .value { font-size: 10px; font-weight: bold; overflow-wrap: break-word; word-wrap: break-word; }
.items { table-layout: fixed; font-size: 9px; }
.items th, .items td { border: 0.6px solid #b8c5d2; padding: 7px 6px; vertical-align: top; overflow-wrap: break-word; word-wrap: break-word; }
.items th { color: #152d49; background: #e8eef5; font-weight: bold; vertical-align: middle; text-align: center; padding: 7px 4px; border-color: #a5b5c5; }
.items tbody tr:nth-child(even) td { background: #f8fafc; }
.items .qty { text-align: right;  }
.items .remark { line-height: 1.5; } thead { display: table-header-group; } tbody { display: table-row-group; }
tr { page-break-inside: avoid; } .items tbody td { height: 19px; }
.after-table { width: 100%; margin-top: 15px; page-break-inside: avoid; }
.after-table td { vertical-align: top; }
.verification { width: 53%; padding-right: 20px; color: #526170; font-size: 8px; }
.verification-box { border-left: 2px solid #c0cddd; padding: 7px 9px; }
.signature { width: 43%; text-align: center; font-size: 9px; }
.signature-title { font-weight: bold; color: #152d49; }
.signature-space { height: 57px; }
.signature-name { border-bottom: 1px solid #53677b; padding-bottom: 4px; font-weight: bold; }
.signature-date { font-size: 8px; margin-top: 5px; color: #35475a; }
.footer { position: fixed; left: 0; right: 0; bottom: -11mm; border-top: 1px solid #607b96; padding-top: 6px; font-size: 7px; color: #43576c; }
.footer td { vertical-align: top; } .page-cell { text-align: right; width: 18%; white-space: nowrap; }
.page:after { content: counter(page); }

.right { text-align: right; }
.muted { font-size: 8px; color: #526170; }
.notice { border: 1px solid #b8c5d2; background: #f5f7fa; padding: 8px 10px; margin: 10px 0 14px; font-size: 8px; page-break-inside: avoid; }
.number-warning { font-size: 8px; font-weight: normal; color: #5b3426; }
.summary { width: 57%; margin: 14px 0 16px auto; font-size: 9px; page-break-inside: avoid; }
.summary td { border-bottom: 0.6px solid #cbd6e2; padding: 5px 7px; }
.summary .grand td { border-top: 1px solid #607b96; border-bottom: 1px solid #607b96; background: #eef3f8; font-weight: bold; color: #152d49; }
.signatures { margin-top: 18px; page-break-inside: avoid; }
.signatures td { width: 50%; padding: 0 18px; text-align: center; vertical-align: top; }
.signatures .sign-space { height: 57px; }
.signatures .sign-name { border-bottom: 1px solid #607b96; padding-bottom: 4px; font-weight: bold; }
.status { margin-top: 6px; font-size: 8px; color: #35475a; }

.request-signoff .signatures td { border: 0; background: #fff; padding: 0 18px; height: auto; }
.request-signoff { background: #fff; padding: 0 0 5px; }
