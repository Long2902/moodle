export function createAutosaveController({
    save,
    onStatus = () => {},
    delay = 800,
    initialRevision = 0,
} = {}) {
    if (typeof save !== 'function') {
        throw new TypeError('Autosave save callback is required');
    }

    let currentRevision = Number(initialRevision) || 0;
    let isBlocked = false;
    let timer = null;
    let pending = null;
    let lane = Promise.resolve();

    const execute = async (payload) => {
        if (isBlocked) {
            return {ok: false, blocked: true, revision: currentRevision};
        }

        onStatus('saving');
        try {
            const result = await save({
                ...payload,
                revision: currentRevision,
            });

            if (result && result.conflict) {
                isBlocked = true;
                currentRevision = Number(result.revision) || currentRevision;
                onStatus('conflict');
                return result;
            }

            if (!result || result.ok !== true) {
                throw new Error('Native save failed');
            }

            currentRevision = Number(result.revision);
            onStatus('saved');
            return result;
        } catch (error) {
            onStatus('error');
            throw error;
        }
    };

    const enqueue = (payload) => {
        lane = lane.then(() => execute(payload));
        return lane;
    };

    const clearPendingTimer = () => {
        if (timer !== null) {
            clearTimeout(timer);
            timer = null;
        }
    };

    return {
        schedule(payload) {
            if (isBlocked) {
                return;
            }
            pending = payload;
            clearPendingTimer();
            timer = setTimeout(() => {
                timer = null;
                const next = pending;
                pending = null;
                enqueue(next).catch(() => {});
            }, delay);
        },

        saveNow(payload) {
            if (isBlocked) {
                return Promise.resolve({
                    ok: false,
                    blocked: true,
                    revision: currentRevision,
                });
            }
            pending = null;
            clearPendingTimer();
            return enqueue(payload);
        },

        revision() {
            return currentRevision;
        },

        blocked() {
            return isBlocked;
        },
    };
}
