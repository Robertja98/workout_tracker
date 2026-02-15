// MotivationPopup.js
// Minimal, self-contained popup for motivation selection during session

const motivationWords = [
    'Strength', 'Energy', 'Confidence', 'Focus', 'Discipline', 'Joy', 'Growth', 'Resilience', 'Health',
    'Endurance', 'Balance', 'Achievement', 'Routine', 'Wellbeing', 'Empowerment', 'Challenge', 'Calm', 'Community', 'Fun',
    'Self-care', 'Progress', 'Determination', 'Inspiration', 'Recovery', 'Mindfulness', 'Pride', 'Purpose', 'Vitality',
    'Motivation', 'Patience', 'Dedication', 'Support', 'Peace', 'Relax', 'Gratitude', 'Happiness', 'Streak', 'Persistence',
    'Momentum', 'Change', 'Goal', 'Milestone', 'Lift', 'Push', 'Drive', 'Effort', 'Grit', 'Stamina'
];

function createMotivationPopup() {
    if (document.getElementById('motivationPopup')) return;
    const popup = document.createElement('div');
    popup.id = 'motivationPopup';
    popup.style.position = 'fixed';
    popup.style.bottom = '1.5em';
    popup.style.right = '1.5em';
    popup.style.zIndex = '2000';
    popup.style.background = '#fff';
    popup.style.borderRadius = '1em';
    popup.style.boxShadow = '0 4px 24px #0002';
    popup.style.padding = '1em 1.2em 1.2em 1.2em';
    popup.style.minWidth = '220px';
    popup.style.maxWidth = '320px';
    popup.style.transition = 'all 0.3s';
    popup.style.fontFamily = 'inherit';

    popup.innerHTML = `
        <div style="display:flex;align-items:center;justify-content:space-between;">
            <span style="font-weight:bold;color:#4bbf73;">Today's Motivation</span>
            <button id="minimizeMotivationPopup" style="background:none;border:none;font-size:1.2em;cursor:pointer;">_</button>
        </div>
        <div id="motivationPopupCloud" style="margin:0.7em 0 0.5em 0;min-height:40px;"></div>
        <div id="motivationPopupPreview" style="display:none;margin-bottom:0.5em;">
            <div style="font-weight:bold;color:#4bbf73;margin-bottom:0.2em;">Selected:</div>
            <div id="motivationPopupPreviewPills"></div>
        </div>
        <div style="text-align:right;">
            <button id="motivationPopupDoneBtn" class="btn btn-blue" style="margin-right:0.5em;">I'm Done</button>
            <button id="motivationPopupChangeBtn" class="btn btn-small" style="display:none;">Change</button>
        </div>
    `;
    document.body.appendChild(popup);

    // Styles for pills
    const style = document.createElement('style');
    style.textContent = `
        #motivationPopupCloud .motivation-word, #motivationPopupPreviewPills .motivation-static-pill {
            display:inline-block; margin:0.2em 0.3em; padding:0.3em 1em; border-radius:16px;
            font-size:1em; font-weight:normal; background:#e6f7ee; color:#222; cursor:pointer; transition:background 0.2s,color 0.2s;
        }
        #motivationPopupCloud .motivation-word.selected, #motivationPopupPreviewPills .motivation-static-pill {
            background:#4bbf73 !important; color:#fff !important; font-weight:bold;
        }
        #motivationPopupPreviewPills .motivation-static-pill { font-size:1.1em; }
        #motivationPopup.minimized { height:2.2em; min-width:120px; max-width:160px; overflow:hidden; padding:0.2em 1em; }
        #motivationPopup.minimized #motivationPopupCloud, #motivationPopup.minimized #motivationPopupPreview, #motivationPopup.minimized #motivationPopupDoneBtn, #motivationPopup.minimized #motivationPopupChangeBtn { display:none !important; }
    `;
    document.head.appendChild(style);

    // Logic
    const cloudDiv = popup.querySelector('#motivationPopupCloud');
    const previewDiv = popup.querySelector('#motivationPopupPreview');
    const previewPills = popup.querySelector('#motivationPopupPreviewPills');
    const doneBtn = popup.querySelector('#motivationPopupDoneBtn');
    const changeBtn = popup.querySelector('#motivationPopupChangeBtn');
    let selected = [];
    let frozen = false;

    function renderCloud() {
        cloudDiv.innerHTML = '';
        motivationWords.slice(0, 18).forEach(word => {
            const span = document.createElement('span');
            span.textContent = word;
            span.className = 'motivation-word' + (selected.includes(word) ? ' selected' : '');
            span.onclick = () => {
                if (frozen) return;
                if (selected.includes(word)) {
                    selected = selected.filter(w => w !== word);
                } else {
                    selected.push(word);
                }
                renderCloud();
                renderPreview();
            };
            cloudDiv.appendChild(span);
        });
    }
    function renderPreview() {
        if (selected.length > 0) {
            previewDiv.style.display = '';
            previewPills.innerHTML = selected.map(word => `<span class='motivation-static-pill'>${word}</span>`).join(' ');
        } else {
            previewDiv.style.display = 'none';
            previewPills.innerHTML = '';
        }
    }
    doneBtn.onclick = function() {
        if (!selected.length) {
            alert('Please select at least one word.');
            return;
        }
        frozen = true;
        cloudDiv.querySelectorAll('.motivation-word').forEach(span => {
            if (!selected.includes(span.textContent)) span.style.opacity = '0.3';
            else span.classList.add('selected');
        });
        doneBtn.style.display = 'none';
        changeBtn.style.display = '';
    };
    changeBtn.onclick = function() {
        frozen = false;
        doneBtn.style.display = '';
        changeBtn.style.display = 'none';
        cloudDiv.querySelectorAll('.motivation-word').forEach(span => {
            span.style.opacity = '1';
            span.classList.remove('selected');
        });
    };
    popup.querySelector('#minimizeMotivationPopup').onclick = function() {
        popup.classList.toggle('minimized');
    };
    renderCloud();
    renderPreview();
}

window.addEventListener('DOMContentLoaded', createMotivationPopup);
