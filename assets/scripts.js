/**
 * Client-side JavaScript for CamBaddies
 * Handles interactivity: modals, filters, infinite scroll, navigation
 */

// Configuration
const API_URL = '/api/rooms';
const WM = 'lRUVu';
const AFFILIATE_TOUR_EMBED = '9oGW';
const AFFILIATE_TOUR_CHAT = 'LQps';
const AFFILIATE_CAMPAIGN = 'lRUVu';

// State
let currentFilters = {
    gender: [],
    region: [],
    tag: [],
    offset: 0,
    limit: 36
};
let isLoading = false;
let allRooms = [];
let loadedUsernames = new Set();
let totalRooms = 0;
let infiniteScrollEnabled = false;

// Popular tags by gender
const popularTags = {
    f: ['latina', 'asian', 'milf', 'teen', 'bigboobs', 'hairy', 'squirt', 'anal', 'ebony', 'mature'],
    m: ['muscle', 'bigcock', 'bear', 'uncut', 'twink', 'daddy', 'latino', 'cum', 'bbc', 'fit'],
    c: ['anal', 'threesome', 'young', 'bbw', 'interracial', 'latina', 'bisexual', 'feet', 'smoke', 'lesbian'],
    t: ['bigcock', 'asian', 'latina', 'cum', 'anal', 'bigass', 'slim', 'ebony', 'mistress', 'new']
};

// Title mapping
const genderTitles = {
    f: 'Female Cams',
    m: 'Male Cams',
    c: 'Couples Cams',
    t: 'Trans Cams',
    '': 'Live Sex Cams'
};

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Get initial state from SSR
    const initialState = window.__INITIAL_STATE__ || {};

    // Initialize state from SSR data
    if (initialState.rooms) {
        allRooms = initialState.rooms;
        initialState.rooms.forEach(room => loadedUsernames.add(room.username));
    }
    totalRooms = initialState.totalRooms || 0;
    currentFilters.offset = initialState.offset || 36;
    currentFilters.gender = initialState.gender ? [initialState.gender] : [];

    // DOM Elements
    const roomsContainer = document.getElementById('rooms-container');
    const roomsLoader = document.getElementById('rooms-loader');
    const loadMoreBtn = document.getElementById('load-more-btn');
    const scrollLoader = document.getElementById('scroll-loader');
    const noRoomsMessage = document.getElementById('no-rooms-message');
    const modal = document.getElementById('room-modal');
    const closeModalBtn = document.getElementById('close-modal');

    // Setup event listeners
    setupNavigation();
    setupDropdownFilters();
    setupModal();
    setupLoadMore();
    setupInfiniteScroll();
    setupRoomCardClicks();

    /**
     * Setup navigation links
     */
    function setupNavigation() {
        // Desktop nav links
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                navigateTo(this.getAttribute('href'));
            });
        });

        // Mobile gender buttons
        document.querySelectorAll('.mobile-gender-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                navigateTo(this.getAttribute('href'));
            });
        });

        // Handle browser back/forward
        window.addEventListener('popstate', function() {
            handleRoute();
            loadRooms(true);
        });
    }

    /**
     * Setup dropdown filters
     */
    function setupDropdownFilters() {
        // Region filter
        const regionBtn = document.getElementById('region-filter-btn');
        const regionDropdown = document.getElementById('region-dropdown');

        if (regionBtn && regionDropdown) {
            regionBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                toggleDropdown(regionBtn, regionDropdown);
            });

            regionDropdown.querySelectorAll('.dropdown-item').forEach(item => {
                item.addEventListener('click', function() {
                    const value = this.getAttribute('data-value');
                    selectDropdownItem(regionDropdown, this);
                    regionBtn.querySelector('span').textContent = value ? this.textContent : 'Region';
                    regionBtn.classList.toggle('active', !!value);
                    currentFilters.region = value ? [value] : [];
                    closeDropdown(regionBtn, regionDropdown);
                    resetAndLoad();
                });
            });
        }

        // Tags filter
        const tagsBtn = document.getElementById('tags-filter-btn');
        const tagsDropdown = document.getElementById('tags-dropdown');

        if (tagsBtn && tagsDropdown) {
            tagsBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                toggleDropdown(tagsBtn, tagsDropdown);
            });

            setupTagsDropdownItems();
        }

        // Age filter
        const ageBtn = document.getElementById('age-filter-btn');
        const ageDropdown = document.getElementById('age-dropdown');

        if (ageBtn && ageDropdown) {
            ageBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                toggleDropdown(ageBtn, ageDropdown);
            });

            ageDropdown.querySelectorAll('.dropdown-item').forEach(item => {
                item.addEventListener('click', function() {
                    const value = this.getAttribute('data-value');
                    selectDropdownItem(ageDropdown, this);
                    ageBtn.querySelector('span').textContent = value ? this.textContent : 'Age';
                    ageBtn.classList.toggle('active', !!value);

                    // Remove age tags and add new one
                    const ageTags = ['teen', 'young', 'milf', 'mature'];
                    currentFilters.tag = currentFilters.tag.filter(t => !ageTags.includes(t));
                    if (value) {
                        currentFilters.tag.push(value);
                    }

                    closeDropdown(ageBtn, ageDropdown);
                    resetAndLoad();
                });
            });
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.dropdown-filter')) {
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                    menu.classList.remove('open');
                });
                document.querySelectorAll('.dropdown-filter-btn').forEach(btn => {
                    btn.classList.remove('open');
                });
            }
        });
    }

    function setupTagsDropdownItems() {
        const tagsBtn = document.getElementById('tags-filter-btn');
        const tagsDropdown = document.getElementById('tags-dropdown');

        if (!tagsDropdown) return;

        tagsDropdown.querySelectorAll('.dropdown-item').forEach(item => {
            item.addEventListener('click', function() {
                const value = this.getAttribute('data-value');
                selectDropdownItem(tagsDropdown, this);
                tagsBtn.querySelector('span').textContent = value ? this.textContent : 'Popular Tags';
                tagsBtn.classList.toggle('active', !!value);

                // Remove non-age tags and add new one
                const ageTags = ['teen', 'young', 'milf', 'mature'];
                currentFilters.tag = currentFilters.tag.filter(t => ageTags.includes(t));
                if (value) {
                    currentFilters.tag.push(value);
                }

                closeDropdown(tagsBtn, tagsDropdown);
                resetAndLoad();
            });
        });
    }

    function populateTagsDropdown(gender) {
        const tagsDropdown = document.getElementById('tags-dropdown');
        const tagsBtn = document.getElementById('tags-filter-btn');

        if (!tagsDropdown || !tagsBtn) return;

        let tags = [];
        if (gender && popularTags[gender]) {
            tags = popularTags[gender];
        } else {
            const allTags = [...popularTags.f, ...popularTags.m, ...popularTags.c, ...popularTags.t];
            tags = [...new Set(allTags)].slice(0, 10);
        }

        tagsDropdown.innerHTML = '<div class="dropdown-item selected" data-value="">All Tags</div>';
        tags.forEach(tag => {
            const item = document.createElement('div');
            item.className = 'dropdown-item';
            item.setAttribute('data-value', tag);
            item.textContent = tag;
            tagsDropdown.appendChild(item);
        });

        tagsBtn.querySelector('span').textContent = 'Popular Tags';
        tagsBtn.classList.remove('active');

        setupTagsDropdownItems();
    }

    function toggleDropdown(btn, dropdown) {
        document.querySelectorAll('.dropdown-menu').forEach(menu => {
            if (menu !== dropdown) menu.classList.remove('open');
        });
        document.querySelectorAll('.dropdown-filter-btn').forEach(b => {
            if (b !== btn) b.classList.remove('open');
        });

        btn.classList.toggle('open');
        dropdown.classList.toggle('open');
    }

    function closeDropdown(btn, dropdown) {
        btn.classList.remove('open');
        dropdown.classList.remove('open');
    }

    function selectDropdownItem(dropdown, selectedItem) {
        dropdown.querySelectorAll('.dropdown-item').forEach(item => {
            item.classList.remove('selected');
        });
        selectedItem.classList.add('selected');
    }

    /**
     * Setup modal
     */
    function setupModal() {
        if (closeModalBtn) {
            closeModalBtn.addEventListener('click', closeRoomModal);
        }

        if (modal) {
            window.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closeRoomModal();
                }
            });
        }

        // ESC key to close modal
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal && modal.style.display === 'block') {
                closeRoomModal();
            }
        });
    }

    /**
     * Setup room card clicks
     */
    function setupRoomCardClicks() {
        if (roomsContainer) {
            roomsContainer.addEventListener('click', function(e) {
                const card = e.target.closest('.room-card');
                if (card) {
                    const roomData = card.getAttribute('data-room');
                    if (roomData) {
                        try {
                            const room = JSON.parse(roomData);
                            openRoomModal(room);
                        } catch (err) {
                            console.error('Error parsing room data:', err);
                        }
                    }
                }
            });
        }
    }

    function openRoomModal(room) {
        const modalTitle = document.getElementById('modal-title');
        const modalEmbed = document.getElementById('modal-embed');
        const modalInfo = document.getElementById('modal-info');
        const startChatBtn = document.getElementById('start-chat-btn');

        if (modalTitle) {
            modalTitle.textContent = room.username;
        }

        if (modalEmbed) {
            modalEmbed.innerHTML = `<iframe src="https://www.cambaddies.net/in/?tour=${AFFILIATE_TOUR_EMBED}&campaign=${AFFILIATE_CAMPAIGN}&track=embed&room=${room.username}&disable_sound=1&mobileRedirect=auto&embed_video_only=1" frameborder="0" scrolling="no" allowfullscreen></iframe>`;
        }

        if (startChatBtn) {
            startChatBtn.textContent = `Start Chat with ${room.username}`;
            startChatBtn.href = `https://www.cambaddies.net/in/?tour=${AFFILIATE_TOUR_CHAT}&campaign=${AFFILIATE_CAMPAIGN}&track=default&room=${room.username}`;
        }

        if (modalInfo) {
            modalInfo.innerHTML = `
                <div class="room-info-title">Room Details</div>
                <p><strong>Username:</strong> ${room.username}</p>
                ${room.age ? `<p><strong>Age:</strong> ${room.age} years</p>` : ''}
                ${room.location ? `<p><strong>Location:</strong> ${room.location}</p>` : ''}
                ${room.country ? `<p><strong>Country:</strong> ${room.country}</p>` : ''}
                <p><strong>Gender:</strong> ${getGenderText(room.gender)}</p>
                <p><strong>Viewers:</strong> ${room.num_users}</p>
                <p><strong>Followers:</strong> ${room.num_followers}</p>
                <p><strong>Time online:</strong> ${formatOnlineTime(room.seconds_online)}</p>
                ${room.spoken_languages ? `<p><strong>Languages:</strong> ${room.spoken_languages}</p>` : ''}
                <div class="room-info-title" style="margin-top: 1rem;">Tags</div>
                <div class="room-tags">
                    ${room.tags.map(tag => `<span class="room-tag">${tag}</span>`).join('')}
                </div>
                <p style="margin-top: 1rem;"><strong>Subject:</strong> ${room.room_subject}</p>
            `;
        }

        if (modal) {
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
    }

    function closeRoomModal() {
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }

        const modalEmbed = document.getElementById('modal-embed');
        if (modalEmbed) {
            modalEmbed.innerHTML = '';
        }
    }

    /**
     * Setup load more button
     */
    function setupLoadMore() {
        if (loadMoreBtn) {
            loadMoreBtn.addEventListener('click', function() {
                loadRooms(false);
                infiniteScrollEnabled = true;
                loadMoreBtn.classList.add('hidden');
            });
        }
    }

    /**
     * Setup infinite scroll
     */
    function setupInfiniteScroll() {
        window.addEventListener('scroll', function() {
            if (!infiniteScrollEnabled || isLoading) return;

            const scrollPosition = window.innerHeight + window.scrollY;
            const documentHeight = document.documentElement.scrollHeight;

            const isMobile = window.innerWidth <= 768;
            const approximateCardHeight = isMobile ? 200 : 280;
            const twoRowsHeight = approximateCardHeight * 2;

            if (scrollPosition >= documentHeight - twoRowsHeight) {
                if (allRooms.length < totalRooms) {
                    loadRooms(false);
                }
            }
        });
    }

    /**
     * Navigate to URL
     */
    function navigateTo(url) {
        window.history.pushState({}, '', url);
        handleRoute();
        loadRooms(true);
        window.scrollTo(0, 0);
    }

    /**
     * Handle route changes
     */
    function handleRoute() {
        const rawPath = window.location.pathname;
        // Normalize path by removing trailing slash for comparison
        const path = rawPath === '/' ? '/' : rawPath.replace(/\/$/, '');

        // Reset filters
        currentFilters.gender = [];
        currentFilters.offset = 0;
        infiniteScrollEnabled = false;

        if (loadMoreBtn) {
            loadMoreBtn.classList.remove('hidden');
        }

        // Update canonical URL (with trailing slash)
        const canonicalUrl = document.getElementById('canonical-url');
        if (canonicalUrl) {
            canonicalUrl.href = 'https://cambaddies.net' + (path === '/' ? '/' : path + '/');
        }

        // Update navigation active states
        updateActiveNavigation(path);

        // Set gender filter based on route
        let genderValue = '';
        const pageTitle = document.getElementById('page-title');
        const roomsTitle = document.getElementById('rooms-title');

        if (path === '/girls') {
            currentFilters.gender = ['f'];
            genderValue = 'f';
        } else if (path === '/men') {
            currentFilters.gender = ['m'];
            genderValue = 'm';
        } else if (path === '/couples') {
            currentFilters.gender = ['c'];
            genderValue = 'c';
        } else if (path === '/trans') {
            currentFilters.gender = ['t'];
            genderValue = 't';
        }

        // Update titles
        const title = genderTitles[genderValue] || genderTitles[''];
        if (pageTitle) pageTitle.textContent = title;
        if (roomsTitle) roomsTitle.textContent = genderValue ? title : 'Featured Rooms';

        // Update tags dropdown
        populateTagsDropdown(genderValue);
    }

    function updateActiveNavigation(path) {
        // path is already normalized (without trailing slash)
        document.querySelectorAll('.nav-link').forEach(link => {
            const linkPath = link.getAttribute('href').replace(/\/$/, '');
            link.classList.toggle('active', linkPath === path);
        });

        document.querySelectorAll('.mobile-gender-btn').forEach(btn => {
            const btnPath = btn.getAttribute('href').replace(/\/$/, '');
            btn.classList.toggle('active', btnPath === path);
        });
    }

    /**
     * Reset and load rooms
     */
    function resetAndLoad() {
        infiniteScrollEnabled = false;
        if (loadMoreBtn) {
            loadMoreBtn.classList.remove('hidden');
        }
        loadRooms(true);
    }

    /**
     * Load rooms from API
     */
    function loadRooms(reset = false) {
        if (isLoading) return;

        isLoading = true;

        if (reset) {
            currentFilters.offset = 0;
            if (roomsContainer) roomsContainer.innerHTML = '';
            allRooms = [];
            loadedUsernames.clear();
        }

        showLoader(reset);

        // Build API URL
        const params = new URLSearchParams();
        params.append('limit', currentFilters.limit);
        params.append('offset', currentFilters.offset);

        if (currentFilters.gender.length > 0) {
            currentFilters.gender.forEach(g => params.append('gender', g));
        }

        if (currentFilters.region.length > 0) {
            currentFilters.region.forEach(r => params.append('region', r));
        }

        if (currentFilters.tag.length > 0) {
            currentFilters.tag.slice(0, 5).forEach(t => params.append('tag', t));
        }

        const apiUrl = `${API_URL}?${params.toString()}`;

        fetch(apiUrl)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                hideLoader();
                totalRooms = data.count || 0;

                // Filter duplicates
                const newRooms = (data.results || []).filter(room => {
                    if (loadedUsernames.has(room.username)) {
                        return false;
                    }
                    loadedUsernames.add(room.username);
                    return true;
                });

                if (newRooms.length > 0) {
                    allRooms = [...allRooms, ...newRooms];
                    displayRooms(newRooms);
                    currentFilters.offset += currentFilters.limit;

                    if (!infiniteScrollEnabled && loadMoreBtn) {
                        if (allRooms.length < totalRooms) {
                            loadMoreBtn.classList.remove('hidden');
                        } else {
                            loadMoreBtn.classList.add('hidden');
                        }
                    }

                    if (noRoomsMessage) {
                        noRoomsMessage.classList.add('hidden');
                    }
                } else if (reset) {
                    if (noRoomsMessage) {
                        noRoomsMessage.classList.remove('hidden');
                    }
                    if (loadMoreBtn) {
                        loadMoreBtn.classList.add('hidden');
                    }
                }

                isLoading = false;
            })
            .catch(error => {
                console.error('Error fetching rooms:', error);
                hideLoader();
                isLoading = false;

                if (noRoomsMessage) {
                    noRoomsMessage.innerHTML = `
                        <div class="no-rooms-title">Error loading rooms</div>
                        <p>There was a problem connecting to the server. Please try again later.</p>
                    `;
                    noRoomsMessage.classList.remove('hidden');
                }
            });
    }

    function showLoader(isReset) {
        if (isReset && roomsLoader) {
            roomsLoader.classList.remove('hidden');
        } else if (scrollLoader) {
            scrollLoader.classList.remove('hidden');
        }
        if (loadMoreBtn) {
            loadMoreBtn.disabled = true;
        }
    }

    function hideLoader() {
        if (roomsLoader) roomsLoader.classList.add('hidden');
        if (scrollLoader) scrollLoader.classList.add('hidden');
        if (loadMoreBtn) loadMoreBtn.disabled = false;
    }

    function displayRooms(rooms) {
        if (!roomsContainer) return;

        rooms.forEach(room => {
            const card = createRoomCard(room);
            roomsContainer.appendChild(card);
        });
    }

    function createRoomCard(room) {
        const card = document.createElement('div');
        card.className = 'room-card fade-in';
        card.setAttribute('data-username', room.username);
        card.setAttribute('data-room', JSON.stringify(room));

        const hours = Math.floor(room.seconds_online / 3600);
        const minutes = Math.floor((room.seconds_online % 3600) / 60);
        const onlineTime = hours > 0 ? `${hours}h ${minutes}m` : `${minutes}m`;

        const displayTags = room.tags.slice(0, 3);

        let badges = '';
        if (room.is_hd) badges += '<span class="badge badge-hd">HD</span>';
        if (room.is_new) badges += '<span class="badge badge-new">NEW</span>';

        let languageHtml = '';
        if (room.spoken_languages) {
            languageHtml = `
                <div class="room-language">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                    </svg>
                    ${room.spoken_languages}
                </div>`;
        }

        card.innerHTML = `
            <div class="room-thumbnail">
                <img src="${room.image_url_360x270}" alt="${room.username} preview" loading="lazy">
                <div class="room-badges">${badges}</div>
                <div class="room-viewers">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                    <span>${room.num_users}</span>
                </div>
            </div>
            <div class="room-details">
                <div class="room-title">${room.username}</div>
                <div class="room-meta">
                    <span>${room.age ? `${room.age} years` : ''}</span>
                    <span>${onlineTime} online</span>
                </div>
                ${languageHtml}
                <div class="room-tags">
                    ${displayTags.map(tag => `<span class="room-tag">${tag}</span>`).join('')}
                </div>
            </div>
        `;

        return card;
    }

    // Utility functions
    function getGenderText(gender) {
        const map = { f: 'Female', m: 'Male', c: 'Couple', t: 'Trans' };
        return map[gender] || gender;
    }

    function formatOnlineTime(seconds) {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        return hours > 0 ? `${hours} hours, ${minutes} minutes` : `${minutes} minutes`;
    }
});
