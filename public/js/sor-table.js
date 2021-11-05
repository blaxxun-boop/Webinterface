document.addEventListener('DOMContentLoaded', function () {
	for (let table of document.getElementsByClassName("sortable")) {
		let headers = table.getElementsByTagName("thead")[0].getElementsByTagName("th")
		for (let col = 0; col < headers.length; ++col) {
			if (!headers[col].classList.contains("nosort")) {
				let sortbutton = document.createElement("span");
				sortbutton.style.userSelect = "none";
				sortbutton.style.fontSize = "60%";
				sortbutton.style.transform = "rotate(90deg)";
				sortbutton.style.display = "inline-block";
				sortbutton.style.verticalAlign = "middle";
				let upbutton = document.createElement("span");
				upbutton.innerHTML = "&#11164;";
				let downbutton = document.createElement("span");
				downbutton.innerHTML = "&#11166;";
				sortbutton.append(upbutton, downbutton);
				headers[col].appendChild(sortbutton);
				headers[col].style.cursor = "pointer";

				let currentSort = null;
				let sort = function (e, ascending) {
					e.stopPropagation();

					if (ascending === null) {
						ascending = !currentSort;
					}
					currentSort = ascending;

					let activeButton = ascending ? upbutton : downbutton;
					let inactiveButton = ascending ? downbutton : upbutton;
					inactiveButton.style.color = "initial";
					activeButton.style.color = "#dea958";

					let tbody = table.getElementsByTagName("tbody")[0]
					let allRows = [...tbody.children];
					let fixedRows = [];
					let dynamicRows = [];
					let pattern_num = true;
					let pattern_version = true;
					let pattern_vec = true;
					let pattern_checkbox = true;
					let pattern_select = true;
					for (let i = 0; i < allRows.length; ++i) {
						if (allRows[i].classList.contains("sortfixed")) {
							fixedRows.push([i, allRows[i]]);
							continue;
						}

						let cell = allRows[i].children[col];
						if (cell.dataset.num === undefined && !cell.innerText.match(/^\s*-?\d+(?:\.\d+)?\s*$/)) {
							pattern_num = false;
						}
						if (!cell.innerText.match(/^\s*-?\d+(?:\.\d+)?\s*,\s*-?\d+(?:\.\d+)?,\s*-?\d+(?:\.\d+)?\s*$/)) {
							pattern_vec = false;
						}
						if (!cell.innerText.match(/^(\d+\.)*\d+$/)) {
							pattern_version = false;
						}
						if (cell.children.length !== 1 || cell.children[0].tagName !== "INPUT" || !["checkbox", "radio"].includes(cell.children[0].type)) {
							pattern_checkbox = false;
						}
						if (cell.children.length !== 1 || cell.children[0].tagName !== "SELECT") {
							pattern_select = false;
						}
						dynamicRows.push(allRows[i]);
					}
					dynamicRows.sort((trA, trB) => {
						let compare = (x, y) => x - y;
						let value = v => v.innerText;
						if (pattern_num) {
							value = v => parseFloat(v.dataset.num || v.innerText);
						} else if (pattern_version) {
							value = v => v.innerText.split('.').reduce((p, n, idx) => p + n * Math.pow(10, -3 * idx), 0);
						} else if (pattern_vec) {
							value = v => {
								let vec = v.innerText.match(/-?\d+/g).map(n => parseFloat(n));
								return Math.sqrt(vec.map(n => Math.pow(n, 2)).reduce((x, y) => x + y, 0));
							};
						} else if (pattern_checkbox) {
							value = v => v.children[0].checked ? 1 : 0;
						} else if (pattern_select) {
							value = v => v.children[0].options[v.children[0].selectedIndex].text;
							compare = (x, y) => x.localeCompare(y);
						} else {
							compare = (x, y) => x.localeCompare(y);
						}
						let ret = compare(value(trA.children[col]), value(trB.children[col]));
						return ascending ? ret : -ret;
					});
					tbody.append(...dynamicRows);

					let insertOffset = fixedRows.length;
					for (let [idx, row] of fixedRows) {
						tbody.insertBefore(row, tbody.children[--insertOffset + idx].nextSibling)
					}
				};

				headers[col].onclick = e => sort(e, null);
				upbutton.onclick = e => sort(e, true);
				downbutton.onclick = e => sort(e, false);
			}
		}
	}
});