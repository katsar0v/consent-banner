(function () {
  var addButton = document.getElementById('kdcb-add-category');
  var tableBody = document.querySelector('#kdcb-categories-table tbody');
  var template = document.getElementById('kdcb-category-row-template');

  if (!addButton || !tableBody || !template) {
    return;
  }

  var addRow = function () {
    var nextIndex = tableBody.querySelectorAll('tr').length;
    var html = template.innerHTML.replace(/__INDEX__/g, String(nextIndex));
    var holder = document.createElement('tbody');
    holder.innerHTML = html.trim();
    if (holder.firstElementChild) {
      tableBody.appendChild(holder.firstElementChild);
    }
  };

  addButton.addEventListener('click', function () {
    addRow();
  });

  tableBody.addEventListener('click', function (event) {
    var target = event.target;
    if (!target || !target.classList || !target.classList.contains('kdcb-remove-row')) {
      return;
    }

    event.preventDefault();
    var row = target.closest('tr');
    if (!row) {
      return;
    }

    row.remove();
  });
})();
