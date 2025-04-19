const modalShow = (target) => {
    if (!target) {
        alert(false);
        return false;
    }

    const body = document.body;
    const scrollWidth = getScrollWidth();
    const modal = document.querySelector(target);

    modal.classList.add('modal_active');
    body.classList.add('modal-open');

    body.style.paddingRight = scrollWidth + 'px';

    setTimeout(() => {
        modal.classList.add('show');
    }, 10);

    const backdrop = document.createElement('div');
    backdrop.className = 'modal-backdrop fade';
    body.appendChild(backdrop);

    setTimeout(() => {
        backdrop.classList.add('show');
    }, 10);
}

const modalHide = (target = false) => {
    const body = document.body;
    const modal = target ? document.querySelector(target) : document.querySelector('.modal_active');
    const backdrop = document.querySelector('.modal-backdrop');

    modal.classList.remove('show');
    backdrop.classList.remove('show');

    setTimeout(() => {
        backdrop.remove();
        body.classList.remove('modal-open');
        body.removeAttribute('style');
        modal.classList.remove('modal_active');
    }, 150);
}

const modalToggle = (target, targetClose = false) => {
    modalHide(targetClose);
    setTimeout(() => {
        modalShow(target);
    }, 150);
}

function getScrollWidth() {
    const div = document.createElement('div');
    div.style.overflowY = 'scroll';
    div.style.width = '50px';
    div.style.height = '50px';
    document.body.append(div);
    const scrollWidth = div.offsetWidth - div.clientWidth;
    div.remove();
    return scrollWidth;
}

document.addEventListener('click', (e) => {
    const btnModal = e.target.closest('[href^="#modal"], [data-modal]');

    if (btnModal) {
        e.preventDefault();

        const target = btnModal.dataset.modal ? btnModal.dataset.modal : btnModal.hash;
        const modalActive = btnModal.closest('.modal_active');

        let delay = 0;

        if (modalActive) {
            const targetClose = '#' + modalActive.id;
            modalHide(targetClose);
            delay = 150;
        }

        setTimeout(() => {
            modalShow(target);
        }, delay);

        return false;
    }

    const closeModal = e.target.closest('.js-modal-close') || e.target.classList.contains('modal_active');
    if (closeModal) {
        modalHide();

        return false;
    }

    const toggleModal = e.target.closest('[data-modal-toggle]');
    if (toggleModal) {
        const target = toggleModal.dataset.modalToggle;
        modalToggle(target);

        return false;
    }
});